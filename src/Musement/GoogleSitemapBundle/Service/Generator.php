<?php
/**
 * This file is part of the MusementGoogleSitemapBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace App\Musement\GoogleSitemapBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/*
* Return data from API for Sitemap 
*/

class Generator
{

    private $logger;
    private $client;
    
    private $citiesEndpoint;
    private $activitiesEndpoint;
    private $headers;
    private $params;
    
    public function __construct(LoggerInterface $logger, ParameterBagInterface $params)
    {
        $this->logger = $logger;
        $this->params = $params;

        $this->citiesEndpoint = 'https://api.musement.com/api/v3/cities?limit=%d';
        $this->activitiesEndpoint = 'https://api.musement.com/api/v3/cities/%d/activities?limit=%d';
        $this->timeout = $this->params->get('connection_timeout');
        $this->client  = HttpClient::create();
        $this->headers = ['User-Agent' => 'Musement Sitemap Service',  'accept' => 'application/json' ];

    }
    
    /**
     * get list of urls from API
     *
     * @param string $locale Language locale
     *
     * @return string $urls Array of urls
     */
    function getUrls($locale)
    {
        $urls = array();
        if(empty($locale)){
            $this->logger->info('Locale is not specified. Using default locale ' . $this->params->get('default_locale'));
            $locale = $this->params->get('default_locale');
        }
        
        $citiesJson = $this->getCities($locale);
        $cities = json_decode($citiesJson);
        foreach($cities as $city) {
             $urls[] = array('loc' => $city->url,
                'changefreq' => $this->params->get('changefreq'),
                'priority' => $this->params->get('cities_priority'),
                'title' => isset($city->name)?$city->name:''
                /*, 'lastmod' => 'now'*/);
             $activitiesJson = $this->getActivities($city->id, $locale);
             $activities = json_decode($activitiesJson);
             foreach($activities->data as $activity){
                $urls[] = array('loc' => $activity->url,
                    'changefreq' => $this->params->get('changefreq'),
                    'priority' => $this->params->get('activities_priority'),
                    'title' =>isset($activity->title)?$activity->title:''
                    /*, 'lastmod' => 'now'*/);
             }
        }
         
       $this->logger->info('Urls where generated successfuly');
       
 
        return $urls;
    }

    /**
     * get cities from API
     *
     * @param string $locale Language locale
     *
     * @param int $limit Cities count limit
     *
     * @return string $content Json response
     */
    private function getCities($locale, $limit = 20)
    {
        ////  https://api.musement.com/api/v3/cities?limit=20
        $url = sprintf($this->citiesEndpoint, $limit);
        $content = null;
        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => $this->params->get('connection_timeout'),
                'headers' => array_merge($this->headers, [
                            'accept-language' => $locale
                ]),
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new \Exception('API request error: ' . $url);
            }
           $content = $response->getContent();
            
        } catch (TransportExceptionInterface $e) {
                $this->logger->critical($e->getMessage(), [
                    // include extra "context" info in your logs
                    'cause' => 'cities api connection',
                ]);
        }
        
        return $content;
    }

    
    /**
     * get activities from API
     *
     * @param int $cityId City ID
     *
     * @param string $locale Language locale
     *
     * @param int $limit Cities count limit
     *
     * @return string $content Json response
     */
    private function getActivities($cityId, $locale, $limit = 20)
    {
        ////  https://api.musement.com/api/v3/cities/{city_id}/activities?limit=20
        $url = sprintf($this->activitiesEndpoint, $cityId, $limit);
        $content = null;   
        try {
            $response = $this->client->request('GET', $url, [
                    'timeout' => $this->params->get('connection_timeout'),
                    'headers' => array_merge($this->headers, [
                            'accept-language' => $locale
                    ]),
            ]);
            if (200 !== $response->getStatusCode()) {
                throw new \Exception('API request error: ' . $url);
            }
           $content = $response->getContent();
           
        } catch (TransportExceptionInterface $e) {
                $this->logger->critical($e->getMessage(), [
                    // include extra "context" info in your logs
                    'cause' => 'activities api connection',
                ]);
        }
        
        return $content;
    }
    
    
}
