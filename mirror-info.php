<?php
// Define $MYSITE and $LAST_UPDATED variables
include_once $_SERVER['DOCUMENT_ROOT'] . '/include/prepend.inc';

// Define $PHP_7_3_VERSION, $PHP_7_3_SHA256 & $RELEASES variables
include_once $_SERVER['DOCUMENT_ROOT'] . '/include/version.inc';

// Text/plain content type for better readability in browsers
header("Content-type: text/plain; charset=utf-8");

// Provide information on local stats setup
$mirror_stats = (int) (isset($_SERVER['MIRROR_STATS']) && $_SERVER['MIRROR_STATS'] == '1');

// SHA256 check last release file (identifies rsync setup problems)
$filename = $_SERVER['DOCUMENT_ROOT'] . '/distributions/' . $RELEASES[7][$PHP_7_3_VERSION]["source"][0]["filename"];
if (!file_exists($filename)) {
	$hash_ok = 0;
} elseif (isset($PHP_7_3_SHA256["tar.bz2"]) &&
		function_exists('hash_file') &&
		in_array('sha256', hash_algos(), true)) {
	$hash_ok = (int)(hash_file('sha256', $filename) === $PHP_7_3_SHA256["tar.bz2"]);
} else {
	$hash_ok = 0;
}

// Does this mirror have sqlite?
// Gets all available sqlite versions for possible future sqlite wrapper
$sqlite = get_available_sqlites();

$exts = join(get_loaded_extensions(), ',');

if (isset($_GET["token"]) && md5($_GET["token"]) === "19a3ec370affe2d899755f005e5cd90e") {
	$retval = run_self_tests();
	$output = isset($_GET["output"]) ? $_GET["output"] : "php";
	switch($output) {
	case "human":
		var_dump($retval);
		break;

	case "php":
	default:
		echo serialize($retval);
	}
	exit(0);
}

echo join('|', array(
    $MYSITE,            	// 0 : CNAME for mirror as accessed (CC, CC1, etc.)
    phpversion(),       	// 1 : PHP version overview
    $LAST_UPDATED,      	// 2 : Update problems
    $sqlite,            	// 3 : SQLite support?
    $mirror_stats,      	// 4 : Optional local stats support
    default_language(), 	// 5 : Mirror language
    'manual-noalias',   	// 6 : /manual alias check is done elsewhere now
    $hash_ok,            	// 7 : Rsync setup problems
    $exts,              	// 8 : List of php extensions separated by comma
    gethostname(),		// 9 : The configured hostname of the local system
    $_SERVER['SERVER_ADDR'],	// 10: The IP address under which we're running
));

function run_self_tests() {
	global $MYSITE;
	global $LAST_UPDATED, $sqlite, $mirror_stats, $hash_ok;

	//$MYSITE = "http://sg.php.net/";
	$content = fetch_contents($MYSITE . "manual/noalias.txt");
	if (is_array($content) || trim($content) !== 'manual-noalias') {
		return array(
			"name" => "Apache manual alias",
			"see"  => $MYSITE . "mirroring-troubles.php#manual-redirect",
			"got"  => $content,
		);
	}

	$ctype = fetch_header($MYSITE . "manual/en/faq.html.php", "content-type");
	if (strpos($ctype, "text/html") === false) {
		return array(
			"name" => "Header weirdness. Pages named '.html.php' are returning wrong status headers",
			"see"  => $MYSITE . "mirroring-troubles.php#content-type",
			"got"  => var_export($ctype, true),
		);
	}

	$ctype = fetch_header($MYSITE . "functions", "content-type");
	if (is_array($ctype)) {
		$ctype = current($ctype);
	}
	if (strpos($ctype, "text/html") === false) {
		return array(
			"name" => "MultiViews on",
			"see"  => $MYSITE . "mirroring-troubles.php#multiviews",
			"got"  => var_export($ctype, true),
		);
	}

	$header = fetch_header($MYSITE . "manual/en/ref.var.php", 0);
	list($ver, $status, $msg) = explode(" ", $header, 3);
	if($status != 200) {
		return array(
			"name" => "Var Handler",
			"see"  => $MYSITE . "mirroring-troubles.php#var",
			"got"  => var_export($header, true),
		);
	}

	return array(
		"servername" => $MYSITE,
		"version"    => phpversion(),
		"updated"    => $LAST_UPDATED,
		"sqlite"     => $sqlite,
		"stats"      => $mirror_stats,
		"language"   => default_language(),
		"rsync"      => $hash_ok,
	);

}
