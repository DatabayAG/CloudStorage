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
    public static function getInstancesFromResponse($response, ilCloudStorageWebDavClient $client)
    {
        global $DIC;
        $return = array();
        if (count($response) == 0) {
            return $return;
        }
        
        $parent_id = 0;

        // get first item as parent
        foreach ($response as $web_url => $props) {
            if (!array_key_exists($web_url,ilCloudStorageWebDavClient::$unique_path_ids)) {
                $parent_id = count(ilCloudStorageWebDavClient::$unique_path_ids)+1;
                ilCloudStorageWebDavClient::$unique_path_ids[$web_url] = $parent_id;
            } else {
                $parent_id = ilCloudStorageWebDavClient::$unique_path_ids[$web_url];
            }
            break;
        }

        array_shift($response);
        
        foreach ($response as $web_url => $props) {
            if (!array_key_exists("{DAV:}getcontentlength", $props)) {//is folder
                $exid_item = new ilCloudStorageWebDavFolder();
                if (!array_key_exists($web_url,ilCloudStorageWebDavClient::$unique_path_ids)) {
                    $id = count(ilCloudStorageWebDavClient::$unique_path_ids)+1;
                    ilCloudStorageWebDavClient::$unique_path_ids[$web_url] = $id;
                } else {
                    $id = ilCloudStorageWebDavClient::$unique_path_ids[$web_url];
                }
                $exid_item->loadFromProperties($web_url, $props, $parent_id, $id);
                //ilCloudStorageWebDavItemCache::store($exid_item); // not used
                $return[] = $exid_item;
            } else { // is file
                $exid_item = new ilCloudStorageWebDavFile();
                if (!array_key_exists($web_url,ilCloudStorageWebDavClient::$unique_path_ids)) {
                    $id = count(ilCloudStorageWebDavClient::$unique_path_ids)+1;
                    ilCloudStorageWebDavClient::$unique_path_ids[$web_url] = $id;
                } else {
                    $id = ilCloudStorageWebDavClient::$unique_path_ids[$web_url];
                }
                $exid_item->loadFromProperties($web_url, $props, $parent_id, $id);
                //ilCloudStorageWebDavItemCache::store($exid_item); // not used
                $return[] = $exid_item;
            }
        }
        $DIC->logger()->root()->log(var_export(ilCloudStorageWebDavClient::$unique_path_ids,true));
        return $return;
    }
}
