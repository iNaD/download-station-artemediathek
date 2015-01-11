<?php

/**
 * @author Daniel Gehn <me@theinad.com>
 * @version 0.1
 * @copyright 2015 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */

class SynoFileHostingARTEMediathek {
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;

    private $LogPath = '/tmp/arte-mediathek.log';
    private $LogEnabled = false;

    private $language = "de";

    private static $languageMap = array(
        'de'    => 'de',
        'fr'    => 'vof',
    );

    public function __construct($Url, $Username = '', $Password = '', $HostInfo = '') {
        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;

        $this->DebugLog("URL: $Url");
    }

    //This function returns download url.
    public function GetDownloadInfo() {
        $ret = FALSE;

        $this->DebugLog("GetDownloadInfo called");

        $ret = $this->Download();

        return $ret;
    }

    public function onDownloaded()
    {
    }

    public function Verify($ClearCookie = '')
    {
        $this->DebugLog("Verifying User");

        return USER_IS_PREMIUM;
    }

    //This function gets the download url
    private function Download() {
        $this->DebugLog("Determining language by url $this->Url");

        preg_match('#http:\/\/(?:www\.)?arte.tv\/guide\/([a-zA-Z]+)#si', $this->Url, $match);

        if(isset($match[1]) && isset(self::$languageMap[$match[1]]))
        {
            $this->language = self::$languageMap[$match[1]];
        }

        $this->DebugLog('Using language ' . $this->language);

        $this->DebugLog("Getting download url");

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->Url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $rawXML = curl_exec($curl);

        if(!$rawXML)
        {
            $this->DebugLog("Failed to retrieve Website. Error Info: " . curl_error($curl));
            return false;
        }

        curl_close($curl);

        if(preg_match('#arte_vp_url=["|\'](.*?)["|\']#si', $rawXML, $match) === 1)
        {
            $curl = curl_init();

            curl_setopt($curl, CURLOPT_URL, $match[1]);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

            $RawJSON = curl_exec($curl);

            if(!$RawJSON)
            {
                $this->DebugLog("Failed to retrieve JSON. Error Info: " . curl_error($curl));
                return false;
            }

            curl_close($curl);

            $data = json_decode($RawJSON);

            $bestSource = array(
                'bitrate'   => -1,
                'url'       => '',
            );

            foreach($data->videoJsonPlayer->VSR as $source)
            {
                if($source->mediaType == "mp4" && mb_strtolower($source->versionShortLibelle) == $this->language && $source->bitrate > $bestSource['bitrate'])
                {

                    $bestSource['bitrate'] = $source->bitrate;
                    $bestSource['url'] = $source->url;

                }
            }

            if($bestSource['url'] !== '')
            {
                $DownloadInfo = array();
                $DownloadInfo[DOWNLOAD_URL] = trim($bestSource['url']);

                return $DownloadInfo;
            }

            $this->DebugLog("Failed to determine best quality: " . json_encode($data->videoJsonPlayer->VSR));

            return FALSE;

        }

        $this->DebugLog("Couldn't identify player meta");

        return FALSE;
    }

    private function DebugLog($message)
    {
        if($this->LogEnabled === true)
        {
            file_put_contents($this->LogPath, $message . "\n", FILE_APPEND);
        }
    }
}
?>
