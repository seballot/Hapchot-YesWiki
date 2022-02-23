<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiHandler;

require_once 'tools/bazar/libs/vendor/XML/Util.php';

// TODO use Symfony XmlEncoder instead
// https://symfony.com/doc/current/components/serializer.html#the-xmlencoder
class PodcastHandler extends YesWikiHandler
{
    public function run()
    {
        if (!$this->wiki->HasAccess("read") || !$this->wiki->page) return null;
        
        $urlrss = $this->wiki->href('podcast');
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $urlrss .= '&amp;id='.$id;
        } elseif (isset($_GET['id_typeannonce'])) {
            $id = $_GET['id_typeannonce'];
            $urlrss .= '&amp;id='.$id;
        } else {
            $id = '';
        }

        if (isset($_GET['nbitem'])) {
            $nbitem = $_GET['nbitem'];
            $urlrss .= '&amp;nbitem='.$nbitem;
        } else {
            $nbitem = $this->wiki->config['BAZ_NB_ENTREES_FLUX_RSS'];
        }

        if (isset($_GET['utilisateur'])) {
            $utilisateur = $_GET['utilisateur'];
            $urlrss .= '&amp;utilisateur='.$utilisateur;
        } else {
            $utilisateur = '';
        }

        // chaine de recherche
        $q = '';
        if (isset($_GET['q']) and !empty($_GET['q'])) {
            $q = $_GET['q'];
            $urlrss .= '&amp;q='.$q;
        }

        if (isset($_GET['query'])) {
            $query = $_GET['query'];
            $urlrss .= '&amp;query='.$query;
            $tabquery = array();
            $tableau = array();
            $tab = explode('|', $query); //découpe la requete autour des |
            foreach ($tab as $req) {
                $tabdecoup = explode('=', $req, 2);
                $tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
            }
            $query = array_merge($tabquery, $tableau);
        } else {
            $query = '';
        }

        $tableau_flux_rss = $this->getService(EntryManager::class)->search([
            'queries'=>$query,
            'formsIds'=>$id,
            'user'=>$utilisateur,
            'keywords'=>$q
        ]);

        $GLOBALS['ordre'] = 'desc';
        $GLOBALS['champ'] = 'date_creation_fiche';
        usort($tableau_flux_rss, 'champCompare');

        // Limite le nombre de résultat au nombre de fiches demandées
        $tableau_flux_rss = array_slice($tableau_flux_rss, 0, $nbitem);

        // setlocale() pour avoir les formats de date valides (w3c) --julien
        setlocale(LC_TIME, 'C');

        // $xml = XML_Util::getXMLDeclaration('1.0', 'UTF-8', 'yes');
        // $xml .= "\r\n  ";
        $xml = XML_Util::createStartElement('rss', array(
            'version' => '2.0',
            'xmlns:atom' => 'http://www.w3.org/2005/Atom', 
            'xmlns:dc' => 'http://purl.org/dc/elements/1.1/', 
            'xmlns:googleplay' => "http://www.google.com/schemas/play-podcasts/1.0",
            'xmlns:itunes' => "http://www.itunes.com/dtds/podcast-1.0.dtd"
        ));
        $xml .= "\r\n    ";
        $xml .= XML_Util::createStartElement('channel');
        $xml .= "\r\n      ";
        $xml .= XML_Util::createTag('title', null, $this->sanitize('Derniers Podcasts'));
        $xml .= "\r\n      ";
        $xml .= XML_Util::createTag('link', null, $this->sanitize($this->wiki->config['BAZ_RSS_ADRESSESITE']));
        $xml .= "\r\n      ";
        $xml .= XML_Util::createTag('description', null, $this->sanitize($this->wiki->config['BAZ_RSS_DESCRIPTIONSITE']));
        $xml .= "\r\n      ";
        $xml .= XML_Util::createTag('language', null, 'fr-FR');
        $xml .= "\r\n      ";
        $xml .= XML_Util::createTag('lastBuildDate', null, gmstrftime('%a, %d %b %Y %H:%M:%S %Z'));
        $xml .= "\r\n      ";
        $xml .= XML_Util::createTag('ttl', null, '60');
        $xml .= "\r\n      ";

        if (count($tableau_flux_rss) > 0) {
            // Creation des items : titre + lien + description + date de publication
            foreach ($tableau_flux_rss as $ligne) {
                $xml .= "\r\n      ";
                $xml .= XML_Util::createStartElement('item');
                $xml .= "\r\n        ";
                $xml .= XML_Util::createTag('title', null, str_replace('&', '&amp;', $this->sanitize($ligne['bf_titre'])));
                $xml .= "\r\n        ";
                $xml .= XML_Util::createTag('description', null, htmlspecialchars($ligne['description']));
                $xml .= "\r\n        ";
                $duration = 0;
                $array = array_reverse(explode(':', $ligne['duration']));
                foreach($array as $key => $dur) {
                    $duration += ((int) $dur ) * pow(60, $key);
                }
                $xml .= XML_Util::createTag('enclosure', [
                    'url' => $ligne['mixcloud_url'],
                    'type' => "audio/mp3",
                    'length' => $duration*1000
                ]);   
                $xml .= XML_Util::createTag('guid', null, $this->wiki->href('', $ligne['id_fiche']));
                $xml .= "\r\n        ";
                $xml .= XML_Util::createTag('pubDate', null, strftime('%a, %d %b %Y %H:%M:%S +0100', strtotime($ligne['date'])));
                $xml .= "\r\n      ";
                $xml .= XML_Util::createEndElement('item');
            }
        } else {
            //pas d'annonces
            $xml .= "\r\n      ";
            $xml .= XML_Util::createStartElement('item');
            $xml .= "\r\n          ";
            $xml .= XML_Util::createTag('title', null, $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
            $xml .= "\r\n          ";
            $xml .= XML_Util::createTag('link', null, '<![CDATA['.$this->wiki->config['base_url'].$this->wiki->config['root_page'].']]>');
            $xml .= "\r\n          ";
            $xml .= XML_Util::createTag('guid', null, '<![CDATA['.$this->wiki->config['base_url'].$this->wiki->config['root_page'].']]>');
            $xml .= "\r\n          ";
            $xml .= XML_Util::createTag('description', null, $this->sanitize(_t('BAZ_PAS_DE_FICHES')));
            $xml .= "\r\n          ";
            $xml .= XML_Util::createTag('pubDate', null, strftime('%a, %d %b %Y %H:%M:%S GMT', strtotime('01/01/%Y')));
            $xml .= "\r\n      ";
            $xml .= XML_Util::createEndElement('item');
        }
        $xml .= "\r\n    ";
        $xml .= XML_Util::createEndElement('channel');
        $xml .= "\r\n  ";
        $xml .= XML_Util::createEndElement('rss');

        header('Content-type: text/xml; charset=UTF-8');

        return str_replace(
            '</image>',
            '</image>'."\n"
            .'    <atom:link href="'. htmlentities((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])
            .'" rel="self" type="application/rss+xml" />',
            $this->sanitize($xml, ENT_QUOTES, 'UTF-8')
        );
    }
    
    private function sanitize($string)
    {
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        return $string;
    }
}
