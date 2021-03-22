$(function(){
    // tablesorter options
    $('#tblPackages').tablesorter({
        sortList: [[0,0]],
    });

    enclosureQuery();
});

function enclosureQuery() {
    $('#tblPackages tbody').html("<tr><td colspan='6'><br><i class='fa fa-spinner fa-spin icon'></i><em>Please wait, retrieving enclosure information ...</em></td><tr>");
    $.getJSON('/plugins/enclosure-led/include/EnclosureQuery.php', {}, function(data) {
        $('#tblPackages tbody').empty();

        for ([enclosure, slots] of Object.entries(data.enclosures)) {
            for ([slot, disk] of Object.entries(slots)) {
                checked = disk.locate ? 'checked' : '';
                $('#tblPackages tbody').append("<tr>"+
                "<td class='package'>"+enclosure+':'+slot+"</td>"+ // slot
                "<td>"+(disk.dev || '')+"</td>"+
                "<td>"+disk.label+"</td>"+
                "<td>"+disk.mountpoint+"</td>"+
                "<td>"+disk.size+"</td>"+
                "<td><input class='pkgcheckbox' id='"+enclosure+':'+slot+"' type='checkbox' "+checked+">"+
                "</tr>");
            }
        }

        // attach switch buttons to every package checkbox all at once
        $('.pkgcheckbox')
            .switchButton({
                labels_placement: 'right',
                on_label: 'On',
                off_label: 'Off'
            })
            .change(function() {
                $.getJSON('/plugins/enclosure-led/include/EnclosureIdent.php', {slot_id: this.id, action: this.checked ? 'on' : 'off' }, function(data) {
                });
            });

    });
}
