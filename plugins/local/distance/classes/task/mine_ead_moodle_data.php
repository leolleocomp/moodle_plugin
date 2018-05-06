<?php
namespace local_distance\task;

use local_distance\job\miner;

class mine_ead_moodle_data extends \core\task\scheduled_task
{
	public function get_name() {
		return get_string('task_desc', 'local_distance');
	}

	public function execute() {
		$miner_job = new miner();
		\core\task\manager::queue_adhoc_task($miner_job);
	}
}