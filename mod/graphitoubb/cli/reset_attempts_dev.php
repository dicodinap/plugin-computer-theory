<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$opts = getopt('', ['instance:', 'user::']);
$instanceid = (int) ($opts['instance'] ?? 0);
$userid     = isset($opts['user']) ? (int) $opts['user'] : 0;

if (!$instanceid) {
    fwrite(STDERR, "usage: --instance=ID [--user=ID]\n");
    exit(1);
}

global $DB;
$where = ['instanceid' => $instanceid];
if ($userid) {
    $where['userid'] = $userid;
}
$attempts = $DB->get_records('graphitoubb_attempt', $where);
$count = 0;
foreach ($attempts as $a) {
    $DB->delete_records('graphitoubb_submission', ['attemptid' => $a->id]);
    $DB->delete_records('graphitoubb_grade_cache', ['attemptid' => $a->id]);
    $DB->delete_records('graphitoubb_event', ['attemptid' => $a->id]);
    $DB->delete_records('graphitoubb_attempt', ['id' => $a->id]);
    $count++;
}
echo "deleted_attempts=$count\n";
