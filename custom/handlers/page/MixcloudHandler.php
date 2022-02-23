<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiHandler;

require_once 'tools/bazar/libs  /vendor/XML/Util.php';

// TODO use Symfony XmlEncoder instead
// https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
class MixcloudHandler extends YesWikiHandler
{
    public function run()
    {
        /***********************************
             config stuff up here 
        ************************************/

        date_default_timezone_set('Europe/Paris');
        $my_podcast = 'hapchot';  /* www.mixcloud.com/THISBIT/ */ 
        $my_feed_url = "https://hapchotwebradio.fr/?BazaR/mixcloud"; /* url where your cron job saves the output feed - used for self-reference */ 
        $language = "fr-FR";


        /***************************************
         nothing to configure below this line
        ****************************************/ 

        include_once('curl.php');

        $user_info = json_decode(curlGet('http://api.mixcloud.com/'.$my_podcast .'/')); 

        $itunes_image = $user_info->pictures->large;
        $my_description = stripslashes($user_info->biog);
        $updated = date(DATE_RSS,strtotime($user_info->updated_time));
        $my_title = $user_info->name;
        $my_link = $user_info->url;
        
        /* write out the outer shell, channel, globals */ 
        $updated= date("D, d M Y H:i:s T",strtotime("now"));
        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
            <rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\"
                xmlns:atom=\"http://www.w3.org/2005/Atom\">	
            <channel>
                <title><![CDATA[$my_title]]></title>
                <link>$my_link</link>
                <description><![CDATA[$my_description]]></description>
                <image>
                    <url>$itunes_image</url>
                    <link>$my_link</link>
                    <description><![CDATA[$my_title]]></description>
                    <title><![CDATA[$my_title]]></title>
                </image>
                <language>$language</language>
                <lastBuildDate>$updated</lastBuildDate>
                <pubDate>$updated</pubDate>
                <itunes:explicit>no</itunes:explicit>
                <atom:link href=\"$my_feed_url\" rel=\"self\" type=\"application/rss+xml\" /> 
                ";

        $nextURL = null;
        do {
            /* First get the info page for this playlist */
            $url = $nextURL ? $nextURL : $my_podcast;
            $my_podcast_page = file_get_contents('http://www.mixcloud.com/'.$url);

            $my_podcast_page = mb_convert_encoding($my_podcast_page, 'HTML-ENTITIES', "UTF-8");

            $doc = new DOMDocument();
            /* hide warnings - html docs likely won't parse correctly */ 
            libxml_use_internal_errors(true);
            $doc->loadHTML($my_podcast_page); 
            
            $xpath = new DOMXpath($doc);

            if ($xpath->query('//div[@class="infinitescroll-end"]')->length == 0) {
                break;
            }
            $nextURL = $xpath->query('//div[@class="infinitescroll-end"]')->item(0)->getAttribute("m-next-page-url");
            
            $episodes = $xpath->query('//span[@class="play-button "]');
                //echo '<p>episodes has '. $episodes->length .'</p>';
            if($episodes->length > 0) {
                //echo '<p>List of episodes:</p><ol>';
                foreach ($episodes as $episode) {
                    //$episode_image = $xpath->query('.//div[@class="card-cloudcast-image"]/a/img',$container);
                    //$large_photo = 'http:' . $episode_image->item(0)->getAttribute("src");
                    $episode_info = $xpath->query('//span[@class="play-button "]');
                        //if ($episode_info->length == 0) { //episodes that are disabled have no title
                    //	continue;
                    //}
                    $e_title = $episode->getAttribute("m-title");
                    $e_url = 'http://www.mixcloud.com'. $episode->getAttribute("m-url");
                    $e_description = json_decode(curlGet('http://api.mixcloud.com'.$episode->getAttribute("m-url")))->description;
                    $e_preview = $episode->getAttribute("m-preview");
                    $length = strpos($e_preview, "preview");
                    $e_server = substr($e_preview,0,$length - 1);
                    $e_server = str_replace("audiocdn", "stream", $e_server);
                    // todo - should not just be 39 magic number, but where 'preview/' is in url
                    $e_identifier = substr($e_preview,$length + 9);
                    $e_identifier = rtrim($e_identifier,".mp3"); 
                    $e_download =  $e_server . '/c/m4a/64/'. $e_identifier .'.m4a'; 
                    $e_original = $e_server . '/c/originals/' . $e_identifier . '.mp3';
                    $item_size = get_Size($e_download);
                    /* if $item_size is 168 this means not found */ 
                    if($item_size > 200) {
                        $episode_update = $xpath->query('.//div[@class="card-stats cf"]/span[@class="card-date"]/time',$container); 
                        if($episode_update) {
                            $pubDate = strtotime($episode_update->item(0)->nodeValue);
                        } else {
                            $pubDate = "false";
                        } 
                        $output .= "<item>
                        <pubDate>". date(DATE_RSS,$pubDate) ."</pubDate>
                        <title><![CDATA[$e_title]]></title>
                        <link>$e_url</link>
                        <description><![CDATA[$e_description]]></description>
                        <itunes:image href=\"$large_photo\" />
                        <enclosure url=\"$e_download\" length=\"$item_size\" type=\"audio/mp4\" />
                        <guid isPermaLink=\"true\">$e_url</guid>
                    </item>
                        ";
                    }
                }
            }
        } while ($nextURL);

        /* seems like we're getting the closing footer too early */
        sleep(2); 

        /* and output the closing footer */
        $output .= "
            </channel>
        </rss>";

        header("Content-Type: application/rss+xml; charset=UTF-8");
        return $output;
    }
}

/*
 * function to get via cUrl 
 * From lastRSS 0.9.1 by Vojtech Semecky, webmaster @ webdot . cz
 * See      http://lastrss.webdot.cz/
 */
 
function curlGet($URL) {
    $ch = curl_init();
    $timeout = 3;
    curl_setopt( $ch , CURLOPT_URL , $URL );
    curl_setopt( $ch , CURLOPT_RETURNTRANSFER , 1 );
    curl_setopt( $ch , CURLOPT_CONNECTTIMEOUT , $timeout );
	/* if you want to force to ipv6, uncomment the following line */ 
	//curl_setopt( $ch , CURLOPT_IPRESOLVE , 'CURLOPT_IPRESOLVE_V6');
    $tmp = curl_exec( $ch );
    curl_close( $ch );
    return $tmp;
}  

/* 
 * function to use cUrl to get the headers of the file 
 */ 
function get_location($url) {
	$my_ch = curl_init();
	curl_setopt($my_ch, CURLOPT_URL,$url);
	curl_setopt($my_ch, CURLOPT_HEADER,         true);
	curl_setopt($my_ch, CURLOPT_NOBODY,         true);
	curl_setopt($my_ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($my_ch, CURLOPT_TIMEOUT,        10);
	$r = curl_exec($my_ch);
	 foreach(explode("\n", $r) as $header) {
		if(strpos($header, 'Location: ') === 0) {
			return trim(substr($header,10)); 
		}
	 }
	return '';
}

function get_size($url) {
	$my_ch = curl_init();
	curl_setopt($my_ch, CURLOPT_URL,$url);
	curl_setopt($my_ch, CURLOPT_HEADER,         true);
	curl_setopt($my_ch, CURLOPT_NOBODY,         true);
	curl_setopt($my_ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($my_ch, CURLOPT_TIMEOUT,        10);
	$r = curl_exec($my_ch);
	 foreach(explode("\n", $r) as $header) {
		if(strpos($header, 'Content-Length:') === 0) {
			return trim(substr($header,16)); 
		}
	 }
	return '';
}

function get_description($url) {
	$fullpage = curlGet($url);
	$dom = new DOMDocument();
	@$dom->loadHTML($fullpage);
	$xpath = new DOMXPath($dom); 
	$tags = $xpath->query('//div[@class="info-description-body"]');
	foreach ($tags as $tag) {
		$my_description .= (trim($tag->nodeValue));
	}	
	
	return utf8_decode($my_description);
}
