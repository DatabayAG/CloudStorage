<?php

declare(strict_types=1);

/**
 * Class ilCloudStorageWebDavItemCache
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCloudStorageWebDavItemCache
{

    const ITEM_CACHE = 'dav_item_cache';
    /**
     * @var array
     */
    protected static $instances = array();


    /**
     * @param ilCloudStorageWebDavItem $davItem
     */
    public static function store(ilCloudStorageWebDavItem $davItem)
    {
        $_SESSION[self::ITEM_CACHE][$davItem->getId()] = serialize($davItem);
    }


    /**
     * @param $id
     *
     * @return bool
     */
    public static function exists($id)
    {
        return (unserialize($_SESSION[self::ITEM_CACHE][$id]) instanceof ilCloudStorageWebDavItem);
    }


    /**
     * @param $id
     *
     * @return ilCloudStorageWebDavItem
     */
    public static function get($id)
    {
        if (self::exists($id)) {
            return unserialize($_SESSION[self::ITEM_CACHE][$id]);
        }

        return null;
    }
}