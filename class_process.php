<?php
class Process {
	public $level	= 0;
	public $uid	= '';
	public $pid	= 0;
	public $ppid	= 0;
	public $cmd	= '';
	public $children = array();

	function insert($process, $level) {
		if ($process->ppid == $this->pid) {
			$process->level = ($this->level + 1);
			$this->children[] = $process;
			$GLOBALS['insert_success'] = true;
		} else {
			foreach($this->children as $child) {
				$child->insert($process, ($this->level));
			}
		}
	}

	function print_children() {
		$process = array();
		$process['PID'] = $this->pid;
		$process['PPID'] = $this->ppid;
		$process['UID'] = $this->uid;
		$process['NAME'] = $this->cmd;
		$process['level'] = $this->level - 1;
		if ($this->pid != 0) array_push($GLOBALS['processes'], $process);

		for ($i = 0; $i < count($this->children); $i++) {
			$this->children[$i]->print_children();
		}
	}
}
?>