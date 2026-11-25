<?php
require_once('class_system.php');
require_once('include_config.php');

class Linux extends System {
	public $is_fetched_uptime 	= 0;
	public $fetched_uptime 	= array();

	public function __construct() {
	}

	function get_osclass() {
		return 'Linux';
	}

	function get_kernelversion() {
		$kernel = trim((string)@file_get_contents('/proc/sys/kernel/osrelease'));
		return 'Linux ' . $kernel;
	}

	function get_resource_hd() {
		exec($GLOBALS['config_path_to_df'] . ' -mP', $df);
		$hd_info = array();

		$types = array();
		$device = array();
		$data = @file('/proc/self/mounts') ?: array();
		foreach($data as $line) {
			$line = preg_split('/\s+/', trim($line));
			if (count($line) < 3) continue;
			$device[$line[1]] = $line[0];
			$types[$line[1]] = $line[2];
		}

		for ($i = 1; $i < sizeof($df); $i++) {
			$el = preg_split('/\s+/', $df[$i], 6);
			if (count($el) < 6) continue;
			if (in_array($el[5], $GLOBALS['config_skip_mpoints'])) continue;

			$percent = substr($el[4], 0, -1);

			$hd_info[$i]['mountpoint'] 		= $el[5];
			$hd_info[$i]['percent_used'] 	= substr($el[4], 0, -1);
			$hd_info[$i]['percent_free'] 	= (100 - (int)$hd_info[$i]['percent_used']);
			$hd_info[$i]['megabyte_free'] 	= $el[3];
			$hd_info[$i]['megabyte_used'] 	= $el[2];
			$hd_info[$i]['megabyte_total'] 	= $el[1];
			$hd_info[$i]['filesystem'] 		= $types[$el[5]] ?? '';
			$hd_info[$i]['device'] 			= $device[$el[5]] ?? '';

			$hd_info[$i]['mountpoint'] 		= '<acronym title=" ' . $GLOBALS['language']['status']['res_phys_device'] . ' ' . $hd_info[$i]['device']
											  . ', ' . $GLOBALS['language']['status']['res_withfs'] . ' ' . $hd_info[$i]['filesystem']
											  . ' ">' . $el[5] . '</acronym>';

		}

		return $hd_info;
	}

	function get_resource_memory() {
		$data_array = array();

		$data = @file('/proc/meminfo') ?: array();
		foreach($data as $line) {
			$line = preg_split('/\s+/', trim($line), 3);
			if (($line[2] ?? '') == 'kB') {

				$data_array[substr($line[0], 0, -1)] = $line[1];
			}
		}
		$mem_info = array();
		$mem_info[0]['mountpoint'] 		= $GLOBALS['language']['status']['res_memphysical'];
		$mem_info[0]['megabyte_total']	= ((int)($data_array['MemTotal'] ?? 0) / 1024);
		$mem_info[0]['megabyte_free'] 	= ((int)($data_array['MemFree'] ?? 0) / 1024);
		$mem_info[0]['megabyte_used'] 	= $mem_info[0]['megabyte_total'] - $mem_info[0]['megabyte_free'];
		$mem_info[0]['percent_used']	= ($mem_info[0]['megabyte_total'] > 0) ? floor(($mem_info[0]['megabyte_used'] / $mem_info[0]['megabyte_total']) * 100) : 0;
		$mem_info[0]['percent_free'] 	= (100 - $mem_info[0]['percent_used']);

		/* Checking to see if we have any swap memory at all. Users without any swap mounted
		 * would previuosly get at division by zero error on the resources page.
		 */
		if ((int)($data_array['SwapTotal'] ?? 0) == 0) return $mem_info;

		$mem_info[1]['mountpoint'] 		= $GLOBALS['language']['status']['res_memvirtual'];
		$mem_info[1]['megabyte_total']	= ((int)$data_array['SwapTotal'] / 1024);
		$mem_info[1]['megabyte_free'] 	= ((int)($data_array['SwapFree'] ?? 0) / 1024);
		$mem_info[1]['megabyte_used'] 	= $mem_info[1]['megabyte_total'] - $mem_info[1]['megabyte_free'];
		$mem_info[1]['percent_used']	= floor(($mem_info[1]['megabyte_used'] / $mem_info[1]['megabyte_total']) * 100);
		$mem_info[1]['percent_free'] 	= (100 - $mem_info[1]['percent_used']);

		return $mem_info;
	}

	function get_resource_network() {
		$net_info = array();

		if ($fd = @fopen('/proc/net/dev', 'r')) {
			while ($buf = fgets($fd, 4096)) {
				if (preg_match('/:/', $buf)) {
					$data = array();

					list($dev_name, $stats_list) = preg_split('/:/', $buf, 2);
					$el = preg_split('/\s+/', trim($stats_list));

					$data['device_name'] = trim($dev_name);
					if ($data['device_name'] == "lo") $data['device_name'] = $data['device_name'] . ' - ' . $GLOBALS['language']['status']['res_netlocnic'];
					else {
						switch (substr($data['device_name'], 0, -1)) {
							case 'eth':
								$data['device_name'] = $data['device_name'] . ' - ' . $GLOBALS['language']['status']['res_netnic'] . ' ' . ((int)substr($data['device_name'], -1) + 1);
								break;
							case 'ppp':
								$data['device_name'] = $data['device_name'] . ' - ' . $GLOBALS['language']['status']['res_netppp'] . ' ' . ((int)substr($data['device_name'], -1) + 1);
								break;
							case 'sit':
								$data['device_name'] = $data['device_name'] . ' - ' . $GLOBALS['language']['status']['res_netsit'];
								break;
						}
					}
					$el = array_pad($el, 16, 0);
					$data['megabyte_recieved']	= ((float)$el[0] / 1024 / 1024);
					$data['megabyte_sent'] 		= ((float)$el[8] / 1024 / 1024);
					$data['error']				= ((int)$el[2] + (int)$el[10]);
					$data['drop']				= ((int)$el[3] + (int)$el[11]);
					array_push($net_info, $data);
				}
			}
			return $net_info;
		} else return array();
	}

	function get_hardware_cpu() {
		$cpu_info 	= array();
		$cpu_num	= -1;

		$data = @file('/proc/cpuinfo') ?: array();
		foreach($data as $line) {
			list($name, $value) = array_pad(preg_split('/\s*:\s*/', trim($line), 2), 2, '');
			if (strlen(trim($name)) == 0) continue;

			if ($name == "processor") {
				$cpu_num++;
			} else {
				if ($cpu_num >= 0) $cpu_info[$cpu_num][$name] = $value;
			}
		}

		return $cpu_info;
	}

	function get_hardware_pci() {
		$pci_info = array();

		/* /proc/pci no longer exists on modern kernels (2.6+). */
		$data = @file('/proc/pci') ?: array();
		$pci_dev = 'error';

		$i = 0;
		foreach($data as $line) {
			$line = trim($line);

			if (substr($line, 0, 3) == "Bus") {
				$pci_dev = $line;
				$pci_info[$pci_dev] = "";
				$i = 0;
			} else {
				$i++;
				if ($i != 1) $pci_info[$pci_dev] .= "<span>            </span>";
				$pci_info[$pci_dev] .=  $line . "<br>";
			}
		}
		unset($pci_info['error']);
		return $pci_info;
	}

	function get_hardware_ide() {
		$ide_info = array();
		$ide_name = 'error';

		/* /proc/ide is gone on modern kernels; readdir(false) is a TypeError in PHP 8. */
		$handle = @opendir('/proc/ide');
		if ($handle === false) return $ide_info;

		while ($file = readdir($handle)) {
			if (preg_match('/^hd/', $file)) {
				$ide_name = '/dev/' . $file;
				$ide_info[$ide_name] = array();

				$buffer = file('/proc/ide/' . $file . '/model');
				$ide_info[$ide_name]['Device name'] = trim($buffer[0]);
				unset($buffer);

				$buffer = file('/proc/ide/' . $file . '/media');
				switch (trim($buffer[0])) {
					case "disk":	$ide_info[$ide_name]['Device type'] = 'Harddisk'; 		break;
					case "cdrom":	$ide_info[$ide_name]['Device type'] = 'Cd-rom'; 		break;
					default:		$ide_info[$ide_name]['Device type'] = trim($buffer[0]);	break;
				}
				unset($buffer);

				$buffer = file('/proc/ide/' . $file . '/capacity');
				$ide_info[$ide_name]['Sectors'] = trim($buffer[0]);
				unset($buffer);

				$buffer = @file('/proc/ide/' . $file . '/cache');
				$ide_info[$ide_name]['Cache size'] = trim($buffer[0]);
				unset($buffer);

				$buffer = @file('/proc/ide/' . $file . '/geometry');
				$temp = preg_split('/\s+/', $buffer[0], 2);
				$ide_info[$ide_name]['Physical geometry'] = trim($temp[1]);
				$temp = preg_split('/\s+/', $buffer[1], 2);
				$ide_info[$ide_name]['Logical geometry'] = trim($temp[1]);
				unset($buffer);
			}
		}
		closedir($handle);

		ksort($ide_info);
		return $ide_info;
	}

	function get_uptime() {
		$this->__fetch_uptime__();
		return $this->fetched_uptime["uptime"];
	}

	function get_idletime() {
		$this->__fetch_uptime__();
		return $this->fetched_uptime["idletime"];
	}

	function get_systemload()	{
		$load = preg_split('/\s+/', trim((string)@file_get_contents('/proc/loadavg')), 5);
		$load = array_pad($load, 3, '');
		return '<acronym title="' . $GLOBALS['language']['status']['uptime_linux'] . '">' . $load[0] . " " . $load[1] . " " . $load[2] . '</acronym>';
	}

	function get_sysctl() {
		$data_array = array();
		exec($GLOBALS['config_path_to_sysctl'] . ' -a', $data);
		foreach ($data as $line) {
			$line = explode(' =', $line, 2);
			$data_array[$line[0]] = $line[1] ?? '';
		}
		ksort($data_array);
		return $data_array;
	}

	function get_kernel_configuration() {
		if (file_exists($GLOBALS['config_path_to_kernel_config'])) {
			$file = file($GLOBALS['config_path_to_kernel_config']);

			$data_array = array();
			$last_id = "General";
			foreach($file as $line) {
				$line = trim($line);
				if ($line == "") continue;

				if ($line[0] == '#') {
					if (trim(substr($line, 1)) == "") continue;
					else {
						if (trim(substr($line, -11)) == "is not set") continue;
						else {
							$last_id = trim(substr($line, 1));
						}
					}
				} else {
					if (!isset($data_array[$last_id])) $data_array[$last_id] = array();
					$line = array_pad(explode('=', $line, 2), 2, '');
					if ($line[1] == 'm') $line[1] = $GLOBALS['language']['status']['krnl_modularized'];
					if ($line[1] == 'y') $line[1] = $GLOBALS['language']['status']['krnl_compiledin'];
					$data_array[$last_id][$line[0]] = $line[1];
				}
			}
			$data_array["&nbsp;"] = array($GLOBALS['language']['status']['krnl_conf_file'] => $GLOBALS['config_path_to_kernel_config']);
			ksort($data_array);
			return $data_array;
		} else return array();
	}

	function have_section_resources()	{ return 1; }
	function have_section_hardware()	{ return 1; }
	function have_section_kernel()		{ return 1; }
	function have_section_processes()	{ return 1; }

	/**************************************************************************
	 *
	 * Private functions intended for internal usage only.
	 *
	 *************************************************************************/

	function __get_kernel_format__($comment) {
		if ($comment[0] == '#') return trim(substr($comment, 1));
		return $comment;
	}

	function __fetch_uptime__() {
		if ($this->is_fetched_uptime == 0) {
			$time = explode(' ', trim((string)@file_get_contents('/proc/uptime')));
			$time = array_pad($time, 2, 0);
			$ticks = $time[0];

			$this->fetched_uptime["uptime"] 	= $this->__convert_seconds__($time[0]);
			$this->fetched_uptime["idletime"] 	= $this->__convert_seconds__($time[1]);

			$this->is_fetched_uptime = 1;
		}
	}
}
?>
