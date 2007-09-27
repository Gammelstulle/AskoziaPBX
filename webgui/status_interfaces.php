#!/usr/local/bin/php
<?php 
/*
	$Id$
	part of m0n0wall (http://m0n0.ch/wall)
	
	Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
	All rights reserved.
	
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:
	
	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.
	
	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.
	
	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

$pgtitle = array("Status", "Interfaces");
require("guiconfig.inc");


function get_network_interface_info($ifdescr) {
	
	global $config, $g;
	
	$ifinfo = array();
	
	/* find out interface name */
	$ifinfo['hwif'] = $config['interfaces'][$ifdescr]['if'];
	$ifinfo['if'] = $ifinfo['hwif'];
	
	/* run netstat to determine link info */
	unset($linkinfo);
	exec("/usr/bin/netstat -I " . $ifinfo['hwif'] . " -nWb -f link", $linkinfo);
	$linkinfo = preg_split("/\s+/", $linkinfo[1]);
	if (preg_match("/\*$/", $linkinfo[0]) || preg_match("/^$/", $linkinfo[0])) {
		$ifinfo['status'] = "down";
	} else {
		$ifinfo['status'] = "up";
	}
	
	$ifinfo['macaddr'] = $linkinfo[3];
	$ifinfo['inpkts'] = $linkinfo[4];
	$ifinfo['inerrs'] = $linkinfo[5];
	$ifinfo['inbytes'] = $linkinfo[6];
	$ifinfo['outpkts'] = $linkinfo[7];
	$ifinfo['outerrs'] = $linkinfo[8];
	$ifinfo['outbytes'] = $linkinfo[9];
	$ifinfo['collisions'] = $linkinfo[10];
	
	
	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['hwif'], $ifconfiginfo);
		
		foreach ($ifconfiginfo as $ici) {
			if (!$ifdescr == 'wireless') {
				/* don't list media/speed for wireless cards, as it always
				   displays 2 Mbps even though clients can connect at 11 Mbps */
				if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
					$ifinfo['media'] = $matches[1];
				} else if (preg_match("/media: Ethernet (.*)/", $ici, $matches)) {
					$ifinfo['media'] = $matches[1];
				}
			}
			if (preg_match("/status: (.*)$/", $ici, $matches)) {
				if ($matches[1] != "active")
					$ifinfo['status'] = $matches[1];
			}
			if (preg_match("/channel (\S*)/", $ici, $matches)) {
				$ifinfo['channel'] = $matches[1];
			}
			if (preg_match("/ssid (\".*?\"|\S*)/", $ici, $matches)) {
				if ($matches[1][0] == '"')
					$ifinfo['ssid'] = substr($matches[1], 1, -1);
				else
					$ifinfo['ssid'] = $matches[1];
			}
		}		
	}
	
	return $ifinfo;
}

function get_wireless_info($ifdescr) {
	
	global $config, $g;
	
	$ifinfo = array();
	$ifinfo['if'] = $config['interfaces'][$ifdescr]['if'];
	
	if ($config['interfaces'][$ifdescr]['mode'] != "hostap") {
		/* get scan list */
		exec("/sbin/ifconfig -v " . $ifinfo['if'] . " list scan", $scanlist);
		
		$ifinfo['scanlist'] = array();
		$title = array_shift($scanlist);
		
		/* determine width of SSID field */
		$ssid_fldwidth = strpos($title, "BSSID");
		
		foreach ($scanlist as $sl) {
			if ($sl) {
				$slent = array();
				
				$slent['ssid'] = trim(substr($sl, 0, $ssid_fldwidth));
				
				$remflds = preg_split("/\s+/", substr($sl, $ssid_fldwidth), 6);
				
				$slent['bssid'] = $remflds[0];
				$slent['channel'] = $remflds[1];
				$slent['rate'] = $remflds[2];
				list($slent['sig'],$slent['noise']) = explode(":", $remflds[3]);
				$slent['int'] = $remflds[4];
				$slent['caps'] = preg_split("/\s+/", $remflds[5]);
				
				$ifinfo['scanlist'][] = $slent;
			}
		}
	}
	
	/* if in hostap mode: get associated stations */
	if ($config['interfaces'][$ifdescr]['mode'] == "hostap") {
		exec("/sbin/ifconfig -v " . $ifinfo['if'] . " list sta", $aslist);
		
		$ifinfo['aslist'] = array();
		array_shift($aslist);
		foreach ($aslist as $as) {
			if ($as) {
				$asa = preg_split("/\s+/", $as);
				$aslent = array();
				$aslent['mac'] = $asa[0];
				$aslent['rate'] = str_replace("M", " Mbps", $asa[3]);
				$aslent['rssi'] = $asa[4];
				$aslent['caps'] = $asa[8];
				$aslent['flags'] = $asa[9];
				$ifinfo['aslist'][] = $aslent;
			}
		}
	}
	
	return $ifinfo;
}

function get_isdn_interface_info($interface) {
	
	$fields = array();
	
	$unit = $interface['unit'];
	unset($unitinfo);
	exec("/sbin/isdnconfig -u $unit", $unitinfolines);
	foreach ($unitinfolines as $line) {
		if (preg_match("/\s:\s/", $line)) {
			$pair = explode(":", $line, 2);
			$pair[0] = trim($pair[0]);
			$pair[1] = trim($pair[1]);
			$fields[$pair[0]] = $pair[1];
		}
	}

	return $fields;
}

function get_analog_interface_info() {
	
	//putenv("TERM=xterm-color"); XXX term needs help
	exec("/sbin/zttool", $unitinfolines);
	return $unitinfolines;/*
	foreach ($unitinfolines as $line) {
		if (preg_match("/\s:\s/", $line)) {
			$pair = explode(":", $line, 2);
			$pair[0] = trim($pair[0]);
			$pair[1] = trim($pair[1]);
			$fields[$pair[0]] = $pair[1];
		}
	}

	return $fields;*/
}

?>
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0"><?

	$i = 0;
	$ifdescrs = array(
		'lan' => 'Network',
		'wireless' => 'Wireless'
	);
	
	foreach ($ifdescrs as $ifdescr => $ifname) {
		$ifinfo = get_network_interface_info($ifdescr);

		if ($i) {
			?><tr>
				<td colspan="8" class="list" height="12"></td>
			</tr><?
		}

		?><tr> 
			<td colspan="2" class="listtopic"><?=htmlspecialchars($ifname);?> Interface</td>
		</tr>
		<tr> 
			<td width="20%" class="vncellt">Status</td>
			<td width="80%" class="listr"><?=htmlspecialchars($ifinfo['status']);?></td>
		</tr><?
		
		if ($ifinfo['macaddr']) {
			?><tr> 
				<td class="vncellt">MAC Address</td>
				<td class="listr"><?=htmlspecialchars($ifinfo['macaddr']);?></td>
              </tr><?
		}
		
		if ($ifinfo['status'] != "down") {
			
			if ($ifinfo['ipaddr']) {
				?><tr> 
					<td class="vncellt">IP Address</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['ipaddr']);?>&nbsp;</td>
				</tr><?
			}
			
			if ($ifinfo['subnet']) {
				?><tr> 
					<td class="vncellt">Subnet Mask</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['subnet']);?></td>
				</tr><?
			}
			
			if ($ifinfo['gateway']) {
				?><tr> 
					<td class="vncellt">Gateway</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['gateway']);?></td>
              </tr><?
			}
			
			if ($ifinfo['media']) {
				?><tr> 
					<td class="vncellt">Media</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['media']);?></td>
				</tr><?
			}
			
			if ($ifinfo['channel']) {
				?><tr> 
					<td class="vncellt">Channel</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['channel']);?></td>
				</tr><?
			}
			
			if ($ifinfo['ssid']) {
				?><tr> 
					<td class="vncellt">SSID</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['ssid']);?></td>
				</tr><?
			}
			
			?><tr> 
				<td class="vncellt">In/Out Packets</td>
				<td class="listr"> 
					<?=htmlspecialchars($ifinfo['inpkts'] . "/" . $ifinfo['outpkts'] . " (" . 
					format_bytes($ifinfo['inbytes']) . "/" . format_bytes($ifinfo['outbytes']) . ")");?>
				</td>
			</tr><?
			
			if (isset($ifinfo['inerrs'])) {
				?><tr> 
					<td class="vncellt">In/Out Errors</td>
					<td class="listr">
						<?=htmlspecialchars($ifinfo['inerrs'] . "/" . $ifinfo['outerrs']);?>
					</td>
				</tr><?
			}
			
			if (isset($ifinfo['collisions'])) {
				?><tr>
					<td class="vncellt">Collisions</td>
					<td class="listr"><?=htmlspecialchars($ifinfo['collisions']);?></td>
				</tr><?
			}
		}
		
		if ($ifdescr == "wireless") {
			
			$ifinfo = get_wireless_info($ifdescr);


			if (isset($ifinfo['scanlist'])) {
			
			?><tr> 
				<td width="22%" valign="top" class="vncellt">Last scan results</td>
				<td width="78%" class="listrpad"> 
					<table width="100%" border="0" cellpadding="0" cellspacing="0">
						<tr> 
							<td width="35%" class="listhdrr">SSID</td>
							<td width="25%" class="listhdrr">BSSID</td>
							<td width="10%" class="listhdrr">Channel</td>
							<td width="10%" class="listhdrr">Rate</td>
							<td width="10%" class="listhdrr">Signal</td>
							<td width="10%" class="listhdrr">Noise</td>
						</tr><?
						
					foreach ($ifinfo['scanlist'] as $ss) {
						?><tr> 
							<td class="listlr" nowrap><?
							if (!$ss['ssid']) 
								echo "<span class=\"gray\">(hidden)</span>";
							else
								echo htmlspecialchars($ss['ssid']);
			    	
							if (strpos($ss['caps'][0], "E") !== false) {
								?><img src="lock.gif" width="7" height="9"><?
							}
							?></td>
							<td class="listr"><?=htmlspecialchars($ss['bssid']);?></td>
							<td class="listr"><?=htmlspecialchars($ss['channel']);?></td>
							<td class="listr"><?=htmlspecialchars($ss['rate']);?></td>
							<td class="listr"><?=htmlspecialchars($ss['sig']);?></td>
							<td class="listr"><?=htmlspecialchars($ss['noise']);?></td>
						</tr><?
					}
                	
					?></table>
				</td>
			</tr><?
			
			}

			if (isset($ifinfo['aslist'])) {

			?><tr> 
				<td width="22%" valign="top" class="vncellt">Associated stations</td>
				<td width="78%" class="listrpad"><?
				
				if (count($ifinfo['aslist']) > 0) {

					?><table width="100%" border="0" cellpadding="0" cellspacing="0">
						<tr>
							<td width="30%" class="listhdrr">MAC address</td>
							<td width="15%" class="listhdrr">Rate</td>
							<td width="15%" class="listhdrr">RSSI</td>
							<td width="20%" class="listhdrr">Flags</td>
							<td width="20%" class="listhdrr">Capabilities</td>
						</tr><?
						
					foreach ($ifinfo['aslist'] as $as) {

						?><tr> 
							<td class="listlr"><?=htmlspecialchars($as['mac']);?></td>
							<td class="listr"><?=htmlspecialchars($as['rate']);?></td>
							<td class="listr"><?=htmlspecialchars($as['rssi']);?></td>
							<td class="listr"><?=htmlspecialchars($as['flags']);?></td>
							<td class="listr"><?=htmlspecialchars($as['caps']);?></td>
						</tr><?
					}

					?></table><br>
					Flags: A = authorized, E = Extended Rate (802.11g), P = Power save mode<br>
					Capabilities: E = ESS (infrastructure mode), I = IBSS (ad-hoc mode), P = privacy (WEP/TKIP/AES),
					S = Short preamble, s = Short slot time<?

				} else {
					
					?>No stations are associated at this time.<?

				}
			}
			
			?></td>
		</tr><?
		
		}
		
		$i++;
	}
	
	?><tr>
		<td colspan="2" class="list" height="12"></td>
	</tr><?
	
	$i = 0;
	$isdninterfaces = isdn_get_interfaces();
	$recognized_units = isdn_get_recognized_unit_numbers();
	foreach ($isdninterfaces as $isdninterface) {
		if (!isset($recognized_units[$isdninterface['unit']])) {
			continue;
		}
		$ifinfo = get_isdn_interface_info($isdninterface);
		
		if ($i) {
			?><tr>
				<td colspan="2" class="list" height="12"></td>
			</tr><?
		}

		?><tr> 
			<td colspan="2" class="listtopic">
				<?="ISDN Unit {$isdninterface['unit']} (". htmlspecialchars($isdninterface['name']) .")";?></td>
		</tr>
		<tr>
			<td class="vncellt">Attached</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['attached']);?></td>
		</tr>
		<tr>
			<td class="vncellt">PH State</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['PH-state']);?></td>
		</tr>
		<tr>
			<td class="vncellt">Dialtone</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['dialtone']);?></td>
		</tr>
		<tr>
			<td class="vncellt">Description</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['description']);?></td>
		</tr>
		<tr>
			<td class="vncellt">Type</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['type']);?></td>
		</tr>
		<tr>
			<td class="vncellt">Driver Type</td>
			<td class="listr"><?
				echo htmlspecialchars($ifinfo['driver_type']);
				if ($isdn_dchannel_modes[$ifinfo['driver_type']]) {
					echo "&nbsp;(". htmlspecialchars($isdn_dchannel_modes[$ifinfo['driver_type']]) .")";
				}
			?></td>
		</tr>
		<tr>
			<td class="vncellt">Channels</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['channels']);?></td>
		</tr>
		<tr>
			<td class="vncellt">Serial</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['serial']);?></td>
		</tr>
		<tr>
			<td class="vncellt">Power Save</td>
			<td class="listr"><?=htmlspecialchars($ifinfo['power_save']);?></td>
		</tr><?
		
		$i++;
	}
	
	/* ?><tr>
		<td colspan="2" class="list" height="12"></td>
	</tr>
	<tr> 
		<td colspan="2" class="listtopic">Analog Units</td>
	</tr>
	<tr>
		<td class="vncellt">RAW OUTPUT</td>
		<td class="listr"><?=htmlspecialchars(print_r_html(get_analog_interface_info()));?></td>
	</tr> */
	

?></table>
<?php include("fend.inc"); ?>
