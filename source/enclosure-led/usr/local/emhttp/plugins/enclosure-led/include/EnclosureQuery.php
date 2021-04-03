<?

class Disk {
    public $dev = null;
    public $label = '';
    public $mountpoint = '';
    public $size = '';
    public $dsn = null;
    public $sas_addr = null;
    public $locate = False;
    public $modelserial = '';
    public $fsavail = '';
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
    $disks = [];
    
    class MD {
        public $dev = null;
        public $name = null;
        public $fsavail = '';
    }

    $mds = [];
    $mdstat = file('/proc/mdstat');
    foreach ($mdstat as $line) {
        // assume that diskName always comes before rdevName
        if (preg_match('/diskName.(\d+)=(.*)/', $line, $match)) {
            $md = new MD();
            $md->name = $match[2];
            $mds[$match[1]] = $md;
        }
        if (preg_match('/rdevName.(\d+)=(.*)/', $line, $match)) {
            $mds[$match[1]]->dev = $match[2];
        }
    }

    exec("lsblk -OJ", $output);
    $devices = json_decode(implode(PHP_EOL, $output));    

    foreach ($devices->blockdevices as $dev) {
        if ($dev->type == 'disk') {
            $disk = new Disk();
            $disk->dev = $dev->name;
            $disk->size = $dev->size;
            $disk->modelserial = $dev->model . '_' . $dev->serial;
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
        } else if ($dev->type == 'md') {
            foreach ($mds as $id => $md) {
                if ($md->name == $dev->name) {
                    $md->fsavail = $dev->fsavail;
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
                    $disk->modelserial = $lsblk_disk->modelserial;
                }
            }
        }
    }
    
    foreach ($mds as $id => $md) {
        foreach ($enclosures as $enclosure => $slots) {
            foreach ($slots as $dsn => $disk) {
                if ($disk->dev == $md->dev) {
                    $disk->mountpoint .= $disk->mountpoint ? ', ' : '' . $md->name;
                    $disk->fsavail = $md->fsavail;
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