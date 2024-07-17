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
     * @param array $response
     *
     * @return ilCloudStorageWebDavFolder[]|ilCloudStorageWebDavFile[]
     */
    public static function getInstancesFromResponse(array $response, array $settings)
    {
        global $DIC;
        $return = array();
        $refId = $settings['refId'];
        if (count($response) == 0) {
            return $return;
        }
        
        $parent_id = 0;

        $cache = ilCloudStorageWebDavClient::getUniqueIdCache($refId);

        // get first item as parent
        foreach ($response as $web_url => $props) {
            if (!array_key_exists($web_url,$cache)) {
                $parent_id = ilCloudStorageWebDavClient::getUniqueId($cache);
                $cache[$web_url] = $parent_id;
            } else {
                $parent_id = $cache[$web_url];
            }
            break;
        }

        array_shift($response);
       
        foreach ($response as $web_url => $props) {
            if (!array_key_exists("{DAV:}getcontentlength", $props)) {//is folder
                $exid_item = new ilCloudStorageWebDavFolder();
                if (!array_key_exists($web_url, $cache)) {
                    $id = ilCloudStorageWebDavClient::getUniqueId($cache);
                    $cache[$web_url] = $id;
                } else {
                    $id = $cache[$web_url];
                }
                $exid_item->loadFromProperties($web_url, $props, $parent_id, $id, $settings);
                //ilCloudStorageWebDavItemCache::store($exid_item); // not used
                $return[] = $exid_item;
            } else { // is file
                $exid_item = new ilCloudStorageWebDavFile();
                if (!array_key_exists($web_url, $cache)) {
                    $id = ilCloudStorageWebDavClient::getUniqueId($cache);
                    $cache[$web_url] = $id;
                } else {
                    $id = $cache[$web_url];
                }
                $exid_item->loadFromProperties($web_url, $props, $parent_id, $id, $settings);
                //ilCloudStorageWebDavItemCache::store($exid_item); // not used
                $return[] = $exid_item;
            }
        }
        ilCloudStorageWebDavClient::storeUniqueIdCache($cache, $refId);
        $DIC->logger()->root()->log(var_export(ilCloudStorageWebDavClient::getUniqueIdCache($refId),true));
        return $return;
    }
}
