<?php
namespace local_distance\models;

class log_buffer
{
	const table = "log_reduzido";

	// TODO verificar se estes últimos
	// campos estão repetidos
	// TODO retirar a utilização de TRIM
	const update = "
		INSERT INTO {".self::table."} (
			id,
			time,
			userid,
			course,
			course_id,
			module,
			action,
			ip,
			cmid
		)
		SELECT
			id,
			timecreated `time`,
			userid,
			courseid `course`,
			? `course_id`,
			TRIM(LEADING 'mod_' FROM component) `module`,
			action,
			ip,
			objectid `cmid`
		FROM {logstore_standard_log}
		WHERE (
			action='loggedout' OR
			action='loggedin' OR
			courseid IN (
				SELECT disciplina_id FROM {".course_id::table."} WHERE course_id = ?
			)
		)
		AND userid IN (
			SELECT aluno_id FROM {".aluno_ids::table."} WHERE course_id = ?
		)
		ON DUPLICATE KEY UPDATE
			time      = VALUES(time),
			userid    = VALUES(userid),
			course    = VALUES(course),
			course_id = VALUES(course_id),
			module    = VALUES(module),
			action    = VALUES(action),
			ip        = VALUES(ip),
			cmid      = VALUES(cmid)
	";
}
