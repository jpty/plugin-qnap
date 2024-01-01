<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class QNAP extends eqLogic {
    /*     * *************************Attributs****************************** */
	public static $_widgetPossibility = array('custom' => true);
	
    /*     * ***********************Methode static*************************** */
	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = jeedom::getTmpFolder('QNAP') . '/dependance';
		if (exec(system::getCmdSudo() . system::get('cmd_check') . '-E "php\-ssh2|php5\-snmp|php\-snmp" | wc -l') >= 1) {
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}
	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder('QNAP') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function update($_eqLogic_id = null) {
		if ($_eqLogic_id == null) {
			$eqLogics = eqLogic::byType('QNAP');
		} else {
			$eqLogics = array(eqLogic::byId($_eqLogic_id));
		}
		foreach ($eqLogics as $qnap) {
			try {
				$qnap->getQNAPInfo();
			} catch (Exception $e) {
				log::add('QNAP', 'error', $e->getMessage());
			}
		}
	}
	
	public static function cron15() {
		foreach (self::byType('QNAP') as $qnap) {
			if ($qnap->getIsEnable() == 1) {
				$cmd = $qnap->getCmd(null, 'refresh');
				if (!is_object($cmd)) {
					continue; 
				}
				$cmd->execCmd();
			}
		}
    }
	
	public function preUpdate() {
		if ($this->getConfiguration('ip') == '') {
			throw new Exception(__('Le champs IP ne peut pas être vide', __FILE__));
		}
		
		if ($this->getConfiguration('snmp') == '') {
			throw new Exception(__('Le champs Communauté SNMP ne peut pas être vide', __FILE__));
		}
		
		if ($this->getConfiguration('fullsnmp') == 0) {
			if ($this->getConfiguration('username') == '') {
				throw new Exception(__("Le champs SSH Nom d'utilisateur ne peut pas être vide", __FILE__));
			}
			if ($this->getConfiguration('password') == '') {
				throw new Exception(__('Le champs SSH Mot de passe ne peut pas être vide', __FILE__));
			}
			if ($this->getConfiguration('portssh') == '') {
				throw new Exception(__('Le champs Port SSH ne peut pas être vide', __FILE__));
			}
		}
		
		$this->nbHDD = $this->getQNAPnbHDD();
		$this->nbFan = $this->getQNAPnbFan();
	}

	public function getQNAPnbHDD() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$community = $this->getConfiguration('snmp');
		$snmpVersion = $this->getConfiguration('snmpversion');
		$NAS = $this->getName();
		
		return $this->execSNMP($IPaddress, $community, "1.3.6.1.4.1.24681.1.2.10.0", $snmpVersion);		
	}
	
	public function getQNAPnbFan() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$community = $this->getConfiguration('snmp');
		$snmpVersion = $this->getConfiguration('snmpversion');
		$NAS = $this->getName();
		
		return $this->execSNMP($IPaddress, $community, "1.3.6.1.4.1.24681.1.2.14", $snmpVersion);		
	}
	
	public function getQNAPInfo() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$login = $this->getConfiguration('username');
		$pwd = $this->getConfiguration('password');
		$port = $this->getConfiguration('portssh');
		$community = $this->getConfiguration('snmp');
		$snmpVersion = $this->getConfiguration('snmpversion');
		$SNMPonly = $this->getConfiguration('fullsnmp');
		$NAS = $this->getName();
		
		// var
		$this->infos = array(
			'cpu' 		=> '',
			'cpumodel'	=> '',
			'ram' 		=> '',
			'ramtot'	=> '',
			'ramused'	=> '',
			'hdd'		=> '',
			'hddtot'	=> '',
			'hddfree'	=> '',
			'os' 		=> '',
			'status'	=> '',
			'model'		=> '',
			'nasversion'	=> '',
			'systemp'	=> '',
			'cputemp'	=> '',
			'uptime'	=> ''
		);

		// oids
		$oidCPU = "1.3.6.1.4.1.24681.1.2.1";
		//$oidCPU = "1.3.6.1.4.1.24681.1.2.1.0";
		$oidCPUinfos = "";
		$oidRAMtot = "1.3.6.1.4.1.24681.1.2.2.0";
		$oidRAMfree = "1.3.6.1.4.1.24681.1.2.3.0";
		$oidOS = "1.3.6.1.4.1.24681.1.2.13.0";
		$oidModel = "1.3.6.1.4.1.24681.1.2.12.0";
		$oidVersion = "1.3.6.1.2.1.47.1.1.1.1.9.1";
		$oidBuild = "";
		$oidSysTemp = "1.3.6.1.4.1.24681.1.2.6.0";
		$oidCPUTemp = "1.3.6.1.4.1.24681.1.2.5.0";
		$oidUptime = "1.3.6.1.2.1.25.1.1.0";
		$oidHDDtotal = "1.3.6.1.4.1.24681.1.2.17.1.4.1";
		$oidHDDfree = "1.3.6.1.4.1.24681.1.2.17.1.5.1";
		$oidHDDTemp = "1.3.6.1.4.1.24681.1.2.11.1.3.";
		$oidHDDsmart = "1.3.6.1.4.1.24681.1.3.11.1.7.";
		$oidHDDnb = "1.3.6.1.4.1.24681.1.2.10.0";
		$oidSysFanSpeed = "1.3.6.1.4.1.24681.1.2.15.";
		$oidSysFanNb = "1.3.6.1.4.1.24681.1.2.14";
		// commands
		$cmdCPUinfos = "cat /proc/cpuinfo |  grep '^model name' | head -1 | awk '{ print $4,$5,$6,$7,$9 }'";
		$cmdRAMtot = "cat /proc/meminfo |  grep '^MemTotal' | awk '{ print $2 }'";
		$cmdRAMfree = "cat /proc/meminfo |  grep '^MemFree' | awk '{ print $2 }'";
		$cmdRAMbuffer = "cat /proc/meminfo |  grep '^Buffers' | awk '{ print $2 }'";
		$cmdRAMcached = "cat /proc/meminfo |  grep '^Cached' | awk '{ print $2 }'";
		$cmdConfig = "getcfg SHARE_DEF defVolMP -f /etc/config/def_share.info";
		$cmdHDD = "df -h ";
		$cmdHDDgrep = " | grep ";
		$cmdOS = "uname -rnsm";
		$cmdModel = "getsysinfo model";
		$cmdVersion = "getcfg system version";
		$cmdBuild = "getcfg system 'Build Number'";
		$cmdSysTemp = "getsysinfo systmp";
		$cmdCPUTemp = "getsysinfo cputmp";
		$cmdUptime = "uptime";
		$cmdHDDvol = "getsysinfo sysvolnum";
		$cmdHDDtotal = "getsysinfo vol_totalsize volume ";
		$cmdHDDfree = "getsysinfo vol_freesize volume ";
		$cmdHDDTemp = "getsysinfo hdtmp ";
		$cmdHDDsmart = "getsysinfo hdsmart ";
		$cmdHDDnb = "getsysinfo hdnum";
		$cmdSysFanNb = "getsysinfo sysfannum";
		$cmdSysFanSpeed = "getsysinfo sysfan ";

		if($SNMPonly == 1) {
			$this->infos['cpu'] = round($this->execSNMP($IPaddress, $community, $oidCPU, $snmpVersion));
			$this->infos['cpumodel'] = "NA in SNMP only";
			$this->infos['model'] = $this->execSNMP($IPaddress, $community, $oidModel, $snmpVersion);
			$this->infos['nasversion'] = $this->execSNMP($IPaddress, $community, $oidVersion, $snmpVersion);
			$this->infos['systemp'] = round(explode(" ",explode("/", $this->execSNMP($IPaddress, $community, $oidSysTemp, $snmpVersion))[0])[0]);
			$this->infos['cputemp'] = round(explode(" ",explode("/", $this->execSNMP($IPaddress, $community, $oidCPUTemp, $snmpVersion))[0])[0]);
			$this->infos['uptime'] = explode(")", trim($this->execSNMP($IPaddress, $community, $oidUptime, $snmpVersion)))[1];
			$this->infos['uptime'] = explode(",", $this->infos['uptime'])[0];

			$ramfree = $this->execSNMP($IPaddress, $community, $oidRAMfree, $snmpVersion);
			$this->infos['ramtot'] = round($this->execSNMP($IPaddress, $community, $oidRAMtot, $snmpVersion));
			$this->infos['ramused'] = round($this->infos['ramtot']-$ramfree);
			$this->infos['ram'] = round(100-($this->infos['ramused']*100/$this->infos['ramtot']));
			$this->infos['ramtot'] = $this->infos['ramtot'].' MB';
			$this->infos['ramused'] = $this->infos['ramused'].' MB';
			
			$this->nbHDDnas = $this->execSNMP($IPaddress, $community, $oidHDDnb, $snmpVersion);
			for($i=1; $i<=$this->nbHDDnas; $i++) {
				$this->infos['hdd'.$i.'temp'] = 0;
				$this->infos['hdd'.$i.'smart'] = '';
				
				$this->infos['hdd'.$i.'temp'] = round(explode(" ",explode("/", $this->execSNMP($IPaddress, $community, $oidHDDTemp.$i, $snmpVersion))[0])[0]);
				$this->infos['hdd'.$i.'smart'] = $this->execSNMP($IPaddress, $community, $oidHDDsmart.$i, $snmpVersion);			
			}
			
			$this->nbSysFan = $this->execSNMP($IPaddress, $community, $oidSysFanNb, $snmpVersion);
			for($i=1; $i<=$this->nbSysFan; $i++) {
				$this->infos['fan'.$i.'speed'] = 0;
				
				$this->infos['fan'.$i.'speed'] = explode(" ", $this->execSNMP($IPaddress, $community, $oidSysFanSpeed.$i, $snmpVersion))[0];
			}
			
			$this->infos['hddfree'] = $this->execSNMP($IPaddress, $community, $oidHDDfree, $snmpVersion);
			$this->infos['hddtot'] = $this->execSNMP($IPaddress, $community, $oidHDDtotal, $snmpVersion);
			$this->infos['hdd'] = round(($this->infos['hddtot']-$this->infos['hddfree'])*100/$this->infos['hddtot']);

			$this->infos['os'] = $this->execSNMP($IPaddress, $community, $oidOS, $snmpVersion);
			$this->infos['status'] = "Up";
			
			$this->updateInfo();
		} else {
			// SSH connection & launch commands
			if ($this->startSSH($IPaddress, $NAS, $login, $pwd, $port)) {
				$this->infos['cpu'] = $this->execSNMP($IPaddress, $community, $oidCPU, $snmpVersion);
				
				$this->infos['cpumodel'] = $this->execSSH($cmdCPUinfos);
				$this->infos['model'] = trim($this->execSSH($cmdModel));
				$this->infos['nasversion'] = trim($this->execSSH($cmdVersion)).' Build '.trim($this->execSSH($cmdBuild));
				$this->infos['systemp'] = round(explode(" ",explode("/", trim($this->execSSH($cmdSysTemp)))[0])[0]);
				$this->infos['cputemp'] = round(explode(" ",explode("/", trim($this->execSSH($cmdCPUTemp)))[0])[0]);
				
				$up = trim($this->execSSH($cmdUptime));
				$up_array = explode(",", $up);
				$up_array2 = explode("up", $up_array[0]);
				$this->infos['uptime'] = trim(str_replace(':', 'h ', $up_array2[1]));
				
				$ramfree = $this->execSNMP($IPaddress, $community, $oidRAMfree, $snmpVersion);
				$this->infos['ramtot'] = round($this->execSNMP($IPaddress, $community, $oidRAMtot, $snmpVersion));
				$this->infos['ramused'] = round($this->infos['ramtot']-$ramfree);
				$this->infos['ram'] = round(($this->infos['ramused']*100/$this->infos['ramtot']));
				$this->infos['ramtot'] = $this->infos['ramtot'].' MB';
				$this->infos['ramused'] = $this->infos['ramused'].' MB';
			
				$hdd_conf = trim($this->execSSH($cmdConfig));
				$hdd_output = $this->execSSH($cmdHDD.$hdd_conf.$cmdHDDgrep."'".$hdd_conf."'");
				$hdd_output_array = explode(" ", $hdd_output);
				foreach ($hdd_output_array as $val) {
						if(strpos($val, '%') !== false) {
							$this->infos['hdd'] = str_replace('%', '', trim($val));
						}
				}
				
				$hdd_vol = trim($this->execSSH($cmdHDDvol));
				$this->infos['hddtot'] = trim($this->execSSH($cmdHDDtotal.$hdd_vol));
				$this->infos['hddfree'] = trim($this->execSSH($cmdHDDfree.$hdd_vol));
				
				$this->nbHDDnas = trim($this->execSSH($cmdHDDnb));
				for($i=1; $i<=$this->nbHDDnas; $i++) {
					$this->infos['hdd'.$i.'temp'] = 0;
					$this->infos['hdd'.$i.'smart'] = '';
					
					$this->infos['hdd'.$i.'temp'] = round(explode(" ",explode("/", trim($this->execSSH($cmdHDDTemp.$i)))[0])[0]);
					$this->infos['hdd'.$i.'smart'] = trim($this->execSSH($cmdHDDsmart.$i));
				}
				
				$this->nbSysFan = trim($this->execSSH($cmdSysFanNb));
				for($i=1; $i<=$this->nbSysFan; $i++) {
					$this->infos['fan'.$i.'speed'] = 0;			
					$this->infos['fan'.$i.'speed'] = explode(" ", trim($this->execSSH($cmdSysFanSpeed.$i)))[0];
				}
				
				$this->infos['os'] = $this->execSSH($cmdOS);	
				$this->infos['status'] = "Up";
			} else {
				$this->infos['status'] = "Down";
			}
			
			$this->updateInfo();
			
			// close SSH
			$this->disconnect($NAS);
		}
	}
	
	// update HTML
	public function updateInfo() {
		foreach ($this->getCmd('info') as $cmd) {
			try {
				$key = $cmd->getLogicalId();
				$value = $this->infos[$key];
				$this->checkAndUpdateCmd($cmd, $value);
				log::add('QNAP', 'debug', 'key '.$key. ' valeur '.$value);
			} catch (Exception $e) {
				log::add('QNAP', 'error', 'Impossible de mettre à jour le champs '.$key);
			}
		}
	}
	
	// execute SNMP command
	private function execSNMP($ip, $com, $oid, $ver) {
		try {
			switch ($ver) {
				case "v1":
					$cmdOutput = snmpwalk($ip, $com, $oid);
					break;
				case "v2":
					$cmdOutput = snmp2_walk($ip, $com, $oid);
					break;
			}
			log::add('QNAP', 'debug', 'Commande SNMP IP='.$ip.' OID='.$oid. ', communauté='.$com.' retourne ' .$cmdOutput[0]);
			$output = explode(':', $cmdOutput[0]);
			$out = trim(trim(trim($output[1]), '"'));
			log::add('QNAP', 'debug', 'out ' .$out);
		} catch (Exception $e) {
			log::add('QNAP', 'error', 'execSNMP retourne '.$e);
		}
		return $out;
	}
	
	// execute SSH command
	private function execSSH($cmd) {
		try {
			$cmdOutput = ssh2_exec($this->SSH, $cmd);
			log::add('QNAP', 'debug', 'Commande '.$cmd);
			stream_set_blocking($cmdOutput, true);
			$output = stream_get_contents($cmdOutput);
			log::add('QNAP', 'debug', 'Retour Commande '.$output);
		} catch (Exception $e) {
			log::add('QNAP', 'error', 'execSSH retourne '.$e);
		}
		return $output;
	}
	
	// establish SSH
	private function startSSH($ip, $name, $user, $pass, $SSHport) {
		try {
			// SSH connection
			if (!$this->SSH = ssh2_connect($ip, $SSHport)) {
				log::add('QNAP', 'error', 'Impossible de se connecter en SSH au NAS '.$name);
				return 0;
			}else{
				// SSH authentication
				if (!ssh2_auth_password($this->SSH, $user, $pass)){
					log::add('QNAP', 'error', 'Mauvais login/password pour '.$name);
					return 0;
				}else{
					log::add('QNAP', 'debug', 'Connexion OK pour '.$name);
					return 1;
				}
			}
		} catch (Exception $e) {
			log::add('QNAP', 'error', 'startSSH retourne '.$e);
		}			
	}
	
	// Close SSH connection
	private function disconnect($name) {
		try {
			if (!ssh2_disconnect($this->SSH)) {
				log::add('QNAP', 'error', 'Erreur de déconnexion pour '.$name);
			}
			$this->SSH = null;
		} catch (Exception $e) {
			log::add('QNAP', 'error', 'disconnect retourne '.$e);
		}
    }
	
	public function reboot() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$login = $this->getConfiguration('username');
		$pwd = $this->getConfiguration('password');
		$port = $this->getConfiguration('portssh');
		$NAS = $this->getName();
		$cmd = "reboot";
		
		// SSH connection & launch commands
		if ($this->startSSH($IPaddress, $NAS, $login, $pwd, $port)) {
			$this->execSSH($cmd);
		}
		
		// close SSH
		$this->disconnect($NAS);
	}
	
	public function halt() {
		// getting configuration
		$IPaddress = $this->getConfiguration('ip');
		$login = $this->getConfiguration('username');
		$pwd = $this->getConfiguration('password');
		$port = $this->getConfiguration('portssh');
		$NAS = $this->getName();
		$cmd = "poweroff";
		
		// SSH connection & launch commands
		if ($this->startSSH($IPaddress, $NAS, $login, $pwd, $port)) {
			$this->execSSH($cmd);
		}
		
		// close SSH
		$this->disconnect($NAS);
	}
	

	
		/*     * *********************Methode d'instance************************* */
	public function preSave() {
		$this->setDisplay('height','800');
		//$this->setDisplay('width','200');
    }
	
	public function postSave() {
		
		$QNAPCmd = $this->getCmd(null, 'status');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'status');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Statut', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('status');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-power');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('15');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'cpu');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'cpu');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('CPU', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('cpu');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-perf');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite( '%' );
			$QNAPCmd->setOrder('1');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'cpumodel');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'cpumodel');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Modèle CPU', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('cpumodel');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-cpu');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('11');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'ram');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'ram');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('RAM', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('ram');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-perf');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite( '%' );
			$QNAPCmd->setOrder('2');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'ramtot');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'ramtot');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Capacité RAM', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('ramtot');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-ram');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('8');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'ramused');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'ramused');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Utilisation RAM', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('ramused');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-ram');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('9');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'hdd');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'hdd');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('HDD', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('hdd');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-perf');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite( '%' );
			$QNAPCmd->setOrder('3');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'hddtot');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'hddtot');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Capacité HDD', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('hddtot');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-hdd');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('6');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'hddfree');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'hddfree');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Espace libre HDD', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('hddfree');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-hdd');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('7');
			$QNAPCmd->save();
		}
				
		$QNAPCmd = $this->getCmd(null, 'os');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'os');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('OS', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('os');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-linux');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('12');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'model');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'model');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Modèle', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('model');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-pc');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('10');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'nasversion');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'nasversion');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Version NAS', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('nasversion');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-linux');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('13');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'cputemp');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'cputemp');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Température CPU', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('cputemp');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-temp');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite('C');
			$QNAPCmd->setOrder('4');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'systemp');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'systemp');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Température Système', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('systemp');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-temp');
			$QNAPCmd->setSubType('numeric');
			$QNAPCmd->setUnite('C');
			$QNAPCmd->setOrder('5');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'uptime');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'uptime');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Uptime', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('uptime');
			$QNAPCmd->setType('info');
			$QNAPCmd->setTemplate('dashboard','qnap-time');
			$QNAPCmd->setSubType('string');
			$QNAPCmd->setOrder('14');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'refresh');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'refresh');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Rafraîchir', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('refresh');
			$QNAPCmd->setType('action');
			$QNAPCmd->setSubType('other');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'reboot');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'reboot');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Redémarrer', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('reboot');
			$QNAPCmd->setType('action');
			$QNAPCmd->setSubType('other');
			$QNAPCmd->setOrder('16');
			$QNAPCmd->save();
		}
		
		$QNAPCmd = $this->getCmd(null, 'poweroff');
		if (!is_object($QNAPCmd)) {
			log::add('QNAP', 'debug', 'poweroff');
			$QNAPCmd = new qnapCmd();
			$QNAPCmd->setName(__('Arrêter', __FILE__));
			$QNAPCmd->setEqLogic_id($this->getId());
			$QNAPCmd->setLogicalId('poweroff');
			$QNAPCmd->setType('action');
			$QNAPCmd->setSubType('other');
			$QNAPCmd->setOrder('17');
			$QNAPCmd->save();
		}
	}
	
	public function postUpdate() {
		for($i=1; $i<=$this->nbHDD; $i++) {
			$QNAPCmd = $this->getCmd(null, 'hdd'.$i.'temp');
			if (!is_object($QNAPCmd)) {
				log::add('QNAP', 'debug', 'hdd'.$i.'temp');
				$QNAPCmd = new qnapCmd();
				$QNAPCmd->setName(__('HDD'.$i.' Température', __FILE__));
				$QNAPCmd->setEqLogic_id($this->getId());
				$QNAPCmd->setLogicalId('hdd'.$i.'temp');
				$QNAPCmd->setType('info');
				$QNAPCmd->setTemplate('dashboard','qnap-temp');
				$QNAPCmd->setSubType('numeric');
				$QNAPCmd->setUnite('C');
				$j=$i+20;
				$QNAPCmd->setOrder(''.$j);
				$QNAPCmd->save();
			}
			
			$QNAPCmd = $this->getCmd(null, 'hdd'.$i.'smart');
			if (!is_object($QNAPCmd)) {
				log::add('QNAP', 'debug', 'hdd'.$i.'smart');
				$QNAPCmd = new qnapCmd();
				$QNAPCmd->setName(__('HDD'.$i.' SMART', __FILE__));
				$QNAPCmd->setEqLogic_id($this->getId());
				$QNAPCmd->setLogicalId('hdd'.$i.'smart');
				$QNAPCmd->setType('info');
				$QNAPCmd->setTemplate('dashboard','qnap-hdd');
				$QNAPCmd->setSubType('string');
				$j=$i+40;
				$QNAPCmd->setOrder(''.$j);
				$QNAPCmd->save();
			}
		}
		
		for($i=1; $i<=$this->nbFan; $i++) {
			$QNAPCmd = $this->getCmd(null, 'fan'.$i.'speed');
			if (!is_object($QNAPCmd)) {
				log::add('QNAP', 'debug', 'fan'.$i.'speed');
				$QNAPCmd = new qnapCmd();
				$QNAPCmd->setName(__('Fan'.$i.' Vitesse', __FILE__));
				$QNAPCmd->setEqLogic_id($this->getId());
				$QNAPCmd->setLogicalId('fan'.$i.'speed');
				$QNAPCmd->setType('info');
				$QNAPCmd->setTemplate('dashboard','qnap-speed');
				$QNAPCmd->setSubType('numeric');
				$QNAPCmd->setUnite('RPM');
				$j=$i+60;
				$QNAPCmd->setOrder(''.$j);
				$QNAPCmd->save();
			}
		}
		
		$cmd = $this->getCmd(null, 'refresh');
		if (is_object($cmd)) { 
			 $cmd->execCmd();
		}
    }
	
}

class qnapCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */


    public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		switch ($this->getLogicalId()) {
			case "reboot":
				$eqLogic->reboot();
				log::add('QNAP','debug','reboot ' . $this->getHumanName());
				break;
			case "poweroff":
				$eqLogic->halt();
				log::add('QNAP','debug','poweroff ' . $this->getHumanName());
				break;
			case "refresh":
				$eqLogic->getQNAPInfo();
				log::add('QNAP','debug','refresh ' . $this->getHumanName());
				break;
 		}
		return true;
	}

    /*     * **********************Getteur Setteur*************************** */
}
