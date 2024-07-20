<?php

declare(strict_types=1);

/**
 * Class ilCloudStorageWebDavItemFactory
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageWebDavItemFactory
{

    /**
     * @return ilCloudStorageWebDavFolder[]|ilCloudStorageWebDavFile[]
     */
    
    public static function getInstancesFromResponse(array $response, ilCloudStorageWebDavClient $client)
    {
        global $DIC;
        $return = array();
        if (count($response) == 0) {
            return $return;
        }
        
        $parent_web_url = '';
        
        // get first item as parent
        foreach ($response as $url => $props) {
            $DIC->logger()->root()->info("C - parent: " . $url);
            $parent_web_url = $url;
            break;
        }

        array_shift($response);
       
        foreach ($response as $web_url => $props) {
            if (!array_key_exists("{DAV:}getcontentlength", $props)) { // is folder
                $exid_item = new ilCloudStorageWebDavFolder();
                $exid_item->loadFromProperties($parent_web_url, $web_url, $props, $client);
                $return[] = $exid_item;
            } else { // is file
                $exid_item = new ilCloudStorageWebDavFile();
                $exid_item->loadFromProperties($parent_web_url, $web_url, $props, $client);
                $return[] = $exid_item;
            }
        }
        //$DIC->logger()->root()->log("C - items: " . var_export($return,true));
        return $return;
    }
}
