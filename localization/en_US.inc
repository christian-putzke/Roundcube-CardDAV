<?php

$labels = array();
$labels['settings']						= 'CardDAV-Settings';
$labels['settings_tab']					= 'CardDAV';
$labels['settings_label']				= 'Label';
$labels['settings_read_only']			= 'Read only';
$labels['settings_curl_not_installed']	= 'The PHP-Extension CURL is not installed! Please install CURL to use the CardDAV-Plugin.';
$labels['addressbook_contacts']			= 'CardDAV-Contacts';
$labels['addressbook_sync']				= 'Synchronize CardDAV-Addressbook';

$messages = array();
$messages['settings_empty_values']		= 'Please fill out all Label and URL';
$messages['settings_saved']				= 'CardDAV-Server settings and vCards saved';
$messages['settings_save_failed']		= 'Failed to save CardDAV-Server settings';
$messages['settings_delete_loading']	= 'Delete CardDAV-Server settings and related local contacts';
$messages['settings_deleted']			= 'CardDAV-Server settings deleted';
$messages['settings_delete_failed']		= 'Failed to delete CardDAV-Server settings';
$messages['settings_no_connection']		= 'Can\'t connect to the CardDAV-Server';
$messages['settings_init_server']		= 'Checking CardDAV-Server connection and import vCards';
$messages['addressbook_synced']			= 'CardDAV-Contacts synchronized';
$messages['addressbook_sync_failed']	= 'An error occurred while synchronizing the CardDAV-Contacts';
$messages['addressbook_sync_loading']	= 'Synchronize CardDAV-Contacts';