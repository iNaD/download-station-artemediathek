<?php

/**
 * @author Daniel Gehn <me@theinad.com>
 * @version 0.2c
 * @copyright 2015 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */

require_once 'provider.php';

class SynoFileHostingARTEMediathek extends TheiNaDProvider {

    protected $LogPath = '/tmp/arte-mediathek.log';

    protected $language = "de";

    protected static $languageMap = array(
        'de'    => 'de',
        'fr'    => 'vof',
    );

    public function GetDownloadInfo() {
        $this->DebugLog("Determining language by url $this->Url");

        preg_match('#http:\/\/(?:www\.)?arte.tv\/guide\/([a-zA-Z]+)#si', $this->Url, $match);

        if(isset($match[1]) && isset(self::$languageMap[$match[1]]))
        {
            $this->language = self::$languageMap[$match[1]];
        }

        $this->DebugLog('Using language ' . $this->language);

        $this->DebugLog("Getting download url");

        $rawXML = $this->curlRequest($this->Url);

        if($rawXML === null)
        {
            return false;
        }

        if(preg_match('#data-embed-base-url=["|\'](.*?)["|\']#si', $rawXML, $match) === 1) {
            $rawXML = $this->curlRequest($match[1]);

            if($rawXML === null)
            {
                return false;
            }

            if (preg_match('#arte_vp_url=["|\'](.*?)["|\']#si', $rawXML, $match) === 1) {
                $RawJSON = $this->curlRequest($match[1]);

                if ($RawJSON === null) {
                    return false;
                }

                $data = json_decode($RawJSON);

                $bestSource = array(
                    'bitrate' => -1,
                    'url' => '',
                );

                foreach ($data->videoJsonPlayer->VSR as $source) {
                    if ($source->mediaType == "mp4" && mb_strtolower($source->versionShortLibelle) == $this->language && $source->bitrate > $bestSource['bitrate']) {
                        $bestSource['bitrate'] = $source->bitrate;
                        $bestSource['url'] = $source->url;
                    }
                }

                if ($bestSource['url'] !== '') {
                    $filename = '';
                    $url = trim($bestSource['url']);
                    $pathinfo = pathinfo($url);

                    $this->DebugLog("Title: " . $data->videoJsonPlayer->VTI . ' Subtitle: ' . $data->videoJsonPlayer->VSU);

                    if (!empty($data->videoJsonPlayer->VTI)) {
                        $filename .= $data->videoJsonPlayer->VTI;
                    }

                    if (!empty($data->videoJsonPlayer->VSU)) {
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

                return FALSE;

            }
        }

        $this->DebugLog("Couldn't identify player meta");

        return FALSE;
    }

}
?>
