<?

class Disk {
    public $dev = null;
    public $label = '';
    public $mountpoint = '';
    public $size = '';
    public $dsn = null;
    public $sas_addr = null;
    public $locate = False;
}

function parse_sg_ses($dev) {
    $data = [];
    exec("sg_ses --join $dev", $output);
    $disk = null;
    foreach ($output as $line) {
        if ($line[0] != ' ') {
            if (preg_match('/Slot (\d+) \[(\d+),(\d+)\]  Element type: Array device slot/', $line, $match)) {
                $dsn = $match[3];
                $disk = new Disk();
                $disk->dsn = $dsn;
                $data[$dsn] = $disk;
            } else {
                $disk = null;
            }
        } else if ($disk) {
            if (preg_match('/SAS address: (.*)/', $line, $match)) {
                $disk->sas_addr = $match[1];
            }
            if (preg_match('/Ident=1/', $line)) {
                $disk->locate = True;
            }
        }
    }
    return $data;
}

function parse_lsscsi() {
    exec("lsscsi -t -g", $output);
    $enclosures = array();
    foreach ($output as $line) {
        if (preg_match('/enclosu\s+sas:(.*?)\s+-\s+(.*)/', $line, $match)) {
            $enclosures[$match[2]] = parse_sg_ses($match[2]);
        }
    }

    foreach ($output as $line) {
        if (preg_match('/disk\s+sas:(.*?)\s+(.*?)\s+/', $line, $match)) {
            foreach ($enclosures as $enclosure => $slots) {
                foreach ($slots as $dsn => $disk) {
                    if ($disk->sas_addr == $match[1]) {
                        $prefix = '/dev/';
                        if (substr($match[2], 0, strlen($prefix)) == $prefix) {
                            $disk->dev = substr($match[2], strlen($prefix));
                        } else {
                            $disk->dev = $match[2];
                        }
                    }
                }
            }
        }
    }
    return $enclosures;
}

function get_enclosures() {
    $enclosures = parse_lsscsi();

    exec("lsblk -OJ", $output);
    $devices = json_decode(implode(PHP_EOL, $output));
    $disks = [];

    foreach ($devices->blockdevices as $dev) {
        if ($dev->type != 'disk')
            continue;
        $disk = new Disk();
        $disk->dev = $dev->name;
        $disk->size = $dev->size;
        $disks[$dev->name] = $disk;
        if ($dev->children) {
            foreach ($dev->children as $part) {
                if (isset($part->label)) {
                    $disk->label .= $disk->label ? ', ' : '' . $part->label;
                }
                if (isset($part->mountpoint)) {
                    $disk->mountpoint .= $disk->mountpoint ? ', ' : '' . $part->mountpoint;
                }
            }
        }
    }

    foreach ($disks as $lsblk_disk) {
        foreach ($enclosures as $enclosure => $slots) {
            foreach ($slots as $dsn => $disk) {
                if ($disk->dev == $lsblk_disk->dev) {
                    $disk->label = $lsblk_disk->label;
                    $disk->mountpoint = $lsblk_disk->mountpoint;
                    $disk->size = $lsblk_disk->size;
                }
            }
        }
    }

    return $enclosures;

}

$return = [
    'enclosures' => get_enclosures()
    ];

echo json_encode($return);

?>