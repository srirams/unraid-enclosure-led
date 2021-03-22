<?

$slot_id = $_GET['slot_id'];
$action = $_GET['action'];

if (preg_match('/(.*?):(.*)/', $slot_id, $match)) {
    $enclosure = $match[1];
    $dsn = $match[2];
    echo($enclosure);
    echo($dsn);
    echo($action);
    if ($action == 'on') {
        exec("sg_ses --dev-slot-num=$dsn --set=ident $enclosure");
    } else {
        exec("sg_ses --dev-slot-num=$dsn --clear=ident $enclosure");
    }
}

?>