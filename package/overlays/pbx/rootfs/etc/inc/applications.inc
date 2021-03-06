<?php
/*
	$Id$
	part of AskoziaPBX (http://askozia.com/pbx)
	
	Copyright (C) 2007-2010 tecema (a.k.a IKT) <http://www.tecema.de>.
	All rights reserved.
	
	Askozia®PBX is a registered trademark of tecema. Any unauthorized use of
	this trademark is prohibited by law and international treaties.
	
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:
	
	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.
	
	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.
	
	3. Redistribution in any form at a charge, that in whole or in part
	   contains or is derived from the software, including but not limited to
	   value added products, is prohibited without prior written consent of
	   tecema.
	
	THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

require_once("functions.inc");

function applications_get_applications($type=false) {

	$all_apps = $GLOBALS['config']['dialplan']['application'];
	$all_apps = is_array($all_apps) ? $all_apps : array();

	if (!$type) {
		$return_apps = $all_apps;
	} else {
		$return_apps = array();
		foreach ($all_apps as $a) {
			if ($a['type'] == $type) {
				$return_apps[] = $a;
			}
		}
	}

	return $return_apps;
}

function applications_get_application($uniqid) {
	global $uniqid_map;

	return $GLOBALS['config']['dialplan']['application'][$uniqid_map[$uniqid]];
}

/**
 * Returns an array of all assigned application extensions
 */
function applications_get_extensions() {
	global $config;
	
	$extensions = array();

	if (is_array($config['dialplan']['application'])) {
		foreach($config['dialplan']['application'] as $app) {
			$extensions[$app['uniqid']] = $app['extension'];
		}
	}

	return $extensions;
}

function applications_generate_default_application($type) {

	$defaultlogic['php'] = <<<EOD

<?php
  require('phpagi.php');

  \$agi = new AGI();

  \$agi->answer();

  \$agi->text2wav("Hello. I'm a text to speech engine!");
  \$agi->say_time();
  sleep(1);
  for(\$i = 0; \$i < 5; \$i++) {
    \$agi-say_digits(\$i);
  }

  \$agi->hangup();

?>

EOD;

	$defaultlogic['plaintext'] = <<<EOD
1,Answer()
2,Echo()
3,Hangup()

EOD;


	$application = array();

	$application['name'] = gettext("New Application");
	$application['uniqid'] = "DIALPLAN-APPLICATION-" . uniqid(rand());
	$application['applicationlogic'] = base64_encode($defaultlogic[$type]);
	$application['type'] = $type;

	return $application;
}

function applications_verify_application($post, $errors) {

	$application = array();

	v_cleanliness(&$post, &$errors);
	v_required('name', gettext('Name'), &$application, &$post, &$errors);
	v_extensionpattern(&$application, &$post, &$errors);
	v_optional('descr', &$application, &$post);
	v_public_access_editor(&$application, &$post, &$errors);
	v_applicationlogic(&$application, &$post, &$errors);

	$trusted = array(
		'uniqid',
		'type'
	);
	foreach ($trusted as $t) {
		$application[$t] = $post[$t];
	}

	return $application;
}

function applications_save_application($application) {
	global $g, $uniqid_map;

	if (applications_get_application($application['uniqid'])) {
		$GLOBALS['config']['dialplan']['application'][$uniqid_map[$application['uniqid']]] = $application;
	} else {
		$GLOBALS['config']['dialplan']['application'][] = $application;
	}

	write_config();
	touch($g['dialplan_dirty_path']);
}

function applications_delete_application($uniqid) {
	global $g, $uniqid_map;

	unset($GLOBALS['config']['dialplan']['application'][$uniqid_map[$uniqid]]);

	write_config();
	touch($g['dialplan_dirty_path']);
}

function applications_disable_application($uniqid) {
	global $g, $uniqid_map;

	if (applications_get_application($uniqid)) {
		$GLOBALS['config']['dialplan']['application'][$uniqid_map[$uniqid]]['disabled'] = true;

		write_config();
		touch($g['dialplan_dirty_path']);
	}
}

function applications_enable_application($uniqid) {
	global $g, $uniqid_map;

	if (applications_get_application($uniqid)) {
		unset($GLOBALS['config']['dialplan']['application'][$uniqid_map[$uniqid]]['disabled']);

		write_config();
		touch($g['dialplan_dirty_path']);
	}
}

?>
