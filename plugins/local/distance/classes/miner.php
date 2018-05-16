<?php

use \local_distance\models\basis;
use \local_distance\models\discipline;
use \local_distance\models\student;
use \local_distance\models\post;
use \local_distance\models\teacher;
use \local_distance\models\aluno_ids;
use \local_distance\models\course_id;
use \local_distance\models\log_buffer;
use \local_distance\models\minified_log;
use \local_distance\models\transational_distance;
use \local_distance\models\mdl_course_categories;

class local_distance_miner
{
	private $chunk_size = 1000;
	private $buffer_clear_count = 0;
	private $miner_log_path = '/var/tmp/moodle_plugin_transational_distance_miner.log';

	public function __construct() {}

	public function init() {
		global $DB;

		echo 'purging data...'.PHP_EOL;
		echo "\tpurging transational distance records...".PHP_EOL;
		$DB->delete_records(transational_distance::table);

		echo "\tpurging temporary data...".PHP_EOL;
		$this->purge_temp_data();

		return $this;
	}

	public function mine($ids = null)
	{
		if (empty($ids)) {
			$course_ids = mdl_course_categories::get_course_list();
		}
		if (is_array($ids)) {
			$course_ids = $ids;
		}
		else if (is_int($ids)) {
			$course_ids = [$ids];
		}

		$this->buffer_clear_count = 0;

		// shared tables (should be views, but...)
		echo "mining for shared views...".PHP_EOL;

		echo "\tmining alunos views...".PHP_EOL;
		$this->populate_students();

		echo "\tmining professores views...".PHP_EOL;
		$this->populate_teachers();

		echo "\tmining posts views...".PHP_EOL;
		$this->populate_posts();

		foreach($course_ids as $id) {
			echo "mining for shared views of ".$id."...".PHP_EOL;

			try {
				// specific tables (should be views, but...)
				echo "\t mining base...".PHP_EOL;
				$this->populate_base($id);

				echo "\t mining disciplinas...".PHP_EOL;
				$this->populate_disciplines($id);

				echo "\t mining alunos_ids...".PHP_EOL;
				$this->populate_alunos_ids($id);

				echo "\t mining id_disciplinas...".PHP_EOL;
				$this->populate_course_ids($id);

				$this->populate_log_reduzido($id);

				echo "\t mining transational_distance...".PHP_EOL;
				$this->populate_transational_distance($id);
			}
			catch (dml_read_exception $e) {
				echo "dml_read_exception".PHP_EOL;
				$this->log_error($e->debuginfo);
			}
			catch (dml_write_exception $e) {
				echo "dml_write_exception".PHP_EOL;
				$this->log_error($e->debuginfo);
			}
			catch (dml_exception $e) {
				echo "dml_exception".PHP_EOL;
				$this->log_error($e->debuginfo);
			}
			catch (moodle_exception $e) {
				echo "moodle_exception".PHP_EOL;
				$this->log_error($e->debuginfo);
			}

			echo "\tDONE!".PHP_EOL;
		}

		// $this->purge_temp_data();
	}

	public function populate_students()
	{
		$this->populate(student::class);

		return $this;
	}

	public function populate_teachers()
	{
		$this->populate(teacher::class);

		return $this;
	}

	public function populate_posts()
	{
		$this->populate(post::class);

		return $this;
	}

	public function populate_base($course_id)
	{
		$this->populate(basis::class, $course_id, basis::handler($course_id));

		return $this;
	}

	public function populate_disciplines($course_id)
	{
		$this->populate(discipline::class, $course_id);

		return $this;
	}

	public function populate_alunos_ids($course_id)
	{
		$this->populate(aluno_ids::class, $course_id);

		return $this;
	}

	public function populate_course_ids($course_id)
	{
		$this->populate(course_id::class, $course_id);

		return $this;
	}

	public function populate_log_reduzido($course_id)
	{
		echo "\t mining log_reduzido...".PHP_EOL;
		$this->populate(log_buffer::class, $course_id);

		echo "\t mining base_log_reduzido...".PHP_EOL;
		$this->populate(minified_log::class, $course_id);

		return $this;
	}

	public function populate_transational_distance($course_id)
	{
		$this->populate(transational_distance::class, $course_id);

		return $this;
	}

	public function purge_temp_data()
	{
		global $DB;

		$DB->delete_records(basis::table);
		$DB->delete_records(discipline::table);
		$DB->delete_records(student::table);
		$DB->delete_records(post::table);
		$DB->delete_records(teacher::table);
		$DB->delete_records(aluno_ids::table);
		$DB->delete_records(course_id::table);
		$DB->delete_records(log_buffer::table);
		$DB->delete_records(minified_log::table);

		return $this;
	}

	private function populate($model, $course_id = null, $handler = null)
	{
		global $DB;

		$this->buffer_clear_count = 0;

		if (isset($course_id)) {
			// TODO  the references to the same value should be
			// replaced for something more elegant in the future
			$rs = $DB->get_recordset_sql($model::get, [$course_id, $course_id, $course_id]);
		}
		else {
			$rs = $DB->get_recordset_sql($model::get);
		}

		if (!$rs->valid()) return;

		// chunk size buffer
		$buffer = [];

		foreach ($rs as $record) {
			if (isset($handler)) {
				$record = $handler($record);
			}

			$buffer[] = $record;

			if (count($buffer) >= $this->chunk_size) {
				$this->store_results($model::table, $buffer);
			}

		}

		if (count($buffer)) {
			$this->store_results($model::table, $buffer);
		}

		echo "\t\tclosing rs...";
		$rs->close();
		echo "DONE!".PHP_EOL;
	}

	private function store_results($table, &$buffer)
	{
		global $DB;

		echo "\t\tinserting records...";
		$DB->insert_records($table, $buffer);
		echo "DONE!".PHP_EOL;
		echo "\t\tclearing buffer ".++$this->buffer_clear_count."...";
		$buffer = [];
		echo 'DONE!'.PHP_EOL;
		
	}

	private function log_error($err)
	{
		echo 'ERROR -> ... logging'.PHP_EOL;
		error_log($err, 3, $this->miner_log_path);
	}
}
