<?php

declare(strict_types=1);

/**
 * OneDrive platform-specific configuration form items.
 * Included by ilCloudStorageConfigGUI::initConfigurationFormByPlatform().
 * Variables available from calling scope: $pl (plugin object), $this (ConfigGUI instance).
 */

// Azure / Microsoft Entra tenant ID
$ti = new ilTextInputGUI($pl->txt('oa2_tenant_id'), 'oa2_tenant_id');
$ti->setRequired(true);
$ti->setMaxLength(255);
$ti->setSize(60);
$ti->setInfo($pl->txt('oa2_tenant_id_info'));
$this->form->addItem($ti);

// Authentication section header
$sh = new ilFormSectionHeaderGUI();
$sh->setTitle($pl->txt('authentication'));
$this->form->addItem($sh);

// Client ID
$ti = new ilTextInputGUI($pl->txt('oa2_client_id'), 'oa2_client_id');
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo($pl->txt('oa2_client_id_odrv_info'));
$this->form->addItem($ti);

// Client Secret
$ti = new ilTextInputGUI($pl->txt('oa2_client_secret'), 'oa2_client_secret');
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$this->form->addItem($ti);

// auth_method is always oauth2 for OneDrive – store as hidden
$hi = new ilHiddenInputGUI('auth_method');
$hi->setValue('oauth2');
$this->form->addItem($hi);

$hi = new ilHiddenInputGUI('oa2_token_request_auth');
$hi->setValue(ilCloudStorageConfig::POST_BODY);
$this->form->addItem($hi);

// Base directory (optional subfolder in OneDrive root)
$ti = new ilTextInputGUI($pl->txt('base_directory'), 'base_directory');
$ti->setRequired(false);
$ti->setMaxLength(255);
$ti->setSize(60);
$ti->setInfo($pl->txt('base_directory_info_odrv'));
$this->form->addItem($ti);
