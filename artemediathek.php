<?php

/**
 * @author Daniel Gehn <me@theinad.com>
 * @version 0.3a
 * @copyright 2015 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */

require_once 'provider.php';

class SynoFileHostingARTEMediathek extends TheiNaDProvider {

    protected $LogPath = '/tmp/arte-mediathek.log';

    protected $language = "de";
    protected $languageShortLibelle = "de";

    protected $platform = null;
    protected $detectedLanguage = null;
    protected $subdomain = null;

    protected static $languageMap = array(
        'de'    => 'de',
        'fr'    => 'fr',
    );

    protected static $languageMapShortLibelle = array(
        'de'    => 'de',
        'fr'    => 'vf',
    );

    protected static $ovShortLibelle = array(
        'og',
        'ov'
    );

    public function GetDownloadInfo() {
    	$this->DebugLog("Determining website and language by url $this->Url.");

        if($this->extractLanguageAndPlatformFormUrl($this->Url) === false) {
            return false;
        }

        if(
            $this->detectedLanguage !== null &&
            isset(self::$languageMap[$this->detectedLanguage]) &&
            isset(self::$languageMapShortLibelle[$this->detectedLanguage])
        ) {
            $this->language = self::$languageMap[$this->detectedLanguage];
            $this->languageShortLibelle = self::$languageMapShortLibelle[$this->detectedLanguage];
        }

        $this->DebugLog('Using language ' . $this->language . ' (' . $this->languageShortLibelle . ').');

        $this->DebugLog("Fetching page content.");

        $rawXML = $this->curlRequest($this->Url);

        if($rawXML === null)
        {
            $this->DebugLog("Failed to retrieve page content.");
            return false;
        }

        if($this->platform == 'theoperaplatform') {
            $data = $this->theoperaplatform($rawXML);
        }
        else if($this->platform == 'arte' && $this->detectedLanguage == 'en') {
            $data = $this->future($rawXML);
        }
        else {
            switch ($this->subdomain) {
                case 'future.':
                case 'tracks.':
                    $data = $this->future($rawXML);
                    break;

                case 'concert.':
                case 'creative.':
                    $data = $this->theoperaplatform($rawXML);
                    break;

                default:
                    $data = $this->arte($rawXML);
            }
        }

        if($data === null) {
            $this->DebugLog("No metadata found.");
            return false;
        }

        $bestSource = array(
            'bitrate' => -1,
            'url' => '',
        );

        foreach ($data->videoJsonPlayer->VSR as $source) {
            $this->DebugLog("Found quality of $source->bitrate with language $source->versionLibelle ($source->versionShortLibelle)");

            $shortLibelleLowercase = mb_strtolower($source->versionShortLibelle);

            if (
                $source->mediaType == "mp4" &&
                (
                    $shortLibelleLowercase == $this->languageShortLibelle ||
                    in_array($shortLibelleLowercase, self::$ovShortLibelle)
                ) &&
                $source->bitrate > $bestSource['bitrate']
            ) {
                $bestSource['bitrate'] = $source->bitrate;
                $bestSource['url'] = $source->url;
            }
        }

        if ($bestSource['url'] !== '') {
            $filename = '';
            $url = trim($bestSource['url']);
            $pathinfo = pathinfo($url);

            $this->DebugLog("Title: " . $data->videoJsonPlayer->VTI . (isset($data->videoJsonPlayer->VSU) ? ' Subtitle: ' . $data->videoJsonPlayer->VSU : ''));

            if (!empty($data->videoJsonPlayer->VTI)) {
                $filename .= $data->videoJsonPlayer->VTI;
            }

            if (isset($data->videoJsonPlayer->VSU) && !empty($data->videoJsonPlayer->VSU)) {
                $filename .= ' - ' . $data->videoJsonPlayer->VSU;
            }


            if (empty($filename)) {
                $filename = $pathinfo['basename'];
            } else {
                $filename .= '.' . $pathinfo['extension'];
            }

            $this->DebugLog("Naming file: " . $filename);

            $DownloadInfo = array();
            $DownloadInfo[DOWNLOAD_URL] = $url;
            $DownloadInfo[DOWNLOAD_FILENAME] = $this->safeFilename($filename);

            return $DownloadInfo;
        }

        $this->DebugLog("Failed to determine best quality: " . json_encode($data->videoJsonPlayer->VSR));

        return false;
    }

    protected function extractLanguageAndPlatformFormUrl($url) {
        if(preg_match('#https?:\/\/(\w+\.)?arte.tv\/(?:guide\/)?([a-zA-Z]+)#si', $url, $match) > 0) {
            $this->platform = 'arte';
            $this->subdomain = $match[1];
            $this->detectedLanguage = isset($match[2]) ? $match[2] : null;
            return true;
        }

        if(preg_match('#https?:\/\/(\w+\.)?theoperaplatform.eu\/([a-zA-Z]+)#si', $url, $match) > 0) {
            $this->platform = 'theoperaplatform';
            $this->subdomain = $match[1];
            $this->detectedLanguage = isset($match[2]) ? $match[2] : null;
            return true;
        }

        $this->DebugLog("Not an arte or the opera platform website.");
        return false;
    }

    protected function arte($rawXML)
    {
        if(preg_match('#data-embed-base-url=["|\'](.*?)["|\']#si', $rawXML, $match) === 1) {
            $rawXML = $this->curlRequest($match[1]);

            if($rawXML === null)
            {
                return false;
            }

            $vpUrl = null;

            if (preg_match('#arte_vp_url=["|\'](.*?)["|\']#si', $rawXML, $match) === 1) {
                $vpUrl = $match[1];
            } else if(preg_match('#arte_vp_url_oembed=["|\'](.*?)["|\']#si', $rawXML, $match) === 1) {
                $vpUrl = $match[1];
            }

            if ($vpUrl != null) {
                if(preg_match('#https:\/\/api\.arte\.tv\/api\/player\/v1\/oembed\/[a-z]{2}\/([A-Za-z0-9-]+)(\?platform=.+)#si', $vpUrl, $match) === 1) {
                    $id = $match[1];
                    $fp = $match[2];

                    $apiUrl = "https://api.arte.tv/api/player/v1/config/" . $this->language . "/" . $id . $fp;

                    $RawJSON = $this->curlRequest($apiUrl);

                    if ($RawJSON === null) {
                        return null;
                    }

                    return json_decode($RawJSON);
                }
            }
        }

        $this->DebugLog("Couldn't identify player meta.");

        return null;
    }

    protected function theoperaplatform($rawXML) {
        if(preg_match('#arte_vp_url=["|\'](.*?)["|\']#si', $rawXML, $match) === 1)
        {
            $this->DebugLog("The player is located at $match[1]");
            $RawJSON = $this->curlRequest($match[1]);
            if($RawJSON === null)
            {
                $this->DebugLog("Couldn't fetch content of $match[1]");
                return null;
            }

            return json_decode($RawJSON);
        }

        $this->DebugLog("Couldn't identify player meta.");

        return null;
    }

    protected function future($rawXML) {
        if(preg_match('#src=["|\']http.*?json_url=(.*?)%3F.*["|\']#si', $rawXML, $match) === 1)
        {
            $playerUrl = urldecode($match[1]);
            $this->DebugLog("The player is located at $playerUrl");
            $RawJSON = $this->curlRequest($playerUrl);
            if($RawJSON === null)
            {
                $this->DebugLog("Couldn't fetch content of $playerUrl");
                return null;
            }

            return json_decode($RawJSON);
        }

        $this->DebugLog("Couldn't identify player meta.");

        return null;
    }

}
