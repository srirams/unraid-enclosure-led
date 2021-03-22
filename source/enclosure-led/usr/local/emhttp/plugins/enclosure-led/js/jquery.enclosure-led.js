$(function(){
    // tablesorter options
    $('#tblPackages').tablesorter({
        sortList: [[0,0]],
        widgets: ['saveSort', 'filter', 'stickyHeaders', 'zebra'],
        widgetOptions: {
            stickyHeaders_filteredToTop: true,
            stickyHeaders_attachTo: null,
            stickyHeaders_offset: ($('#header').css("position") === "fixed") ? '90' : '0',
            filter_hideEmpty: true,
            filter_liveSearch: true,
            filter_saveFilters: true,
            filter_reset: '.reset',
            filter_functions: {
                '.filter-version': true,
                '.filter-downloaded': true,
                '.filter-installed': true
            }
        }
    });

    // select all packages switch
    $('.checkall')
        .switchButton({
            labels_placement: 'right',
            on_label: 'Select All',
            off_label: 'Select All',
            checked: $.cookie('devpack_checkall') == 'yes'
        })
        .change(function () {
            var myval = $(this)[0].checked;
            $.cookie('devpack_checkall', myval ? 'yes' : 'no', { expires: 3650 });
            $('#tblPackages tbody td:visible .pkgcheckbox').switchButton({checked: myval});
        });

    $('#btnApply').click(Apply);

    packageQuery();
});

//list all available packages in a table
function packageQuery(force) {
    $('#tblPackages tbody').html("<tr><td colspan='6'><br><i class='fa fa-spinner fa-spin icon'></i><em>Please wait, retrieving plugin information ...</em></td><tr>");
    $.getJSON('/plugins/DevPack/include/PackageQuery.php', {force: force}, function(data) {
        $('#tblPackages tbody').empty();
        var Ready;
        var Count = 0;
        var len = data.packages.length, i = 0;
        for (i; i < len; i++) {
            var Update;
            var Downloaded = data.packages[i].downloaded;
            var DownloadEQ = data.packages[i].downloadeq;
            var Installed  = data.packages[i].installed;
            var InstallEQ  = data.packages[i].installeq;
            if (DownloadEQ == Downloaded && InstallEQ == Installed){
                if (Installed == "yes"){
                    if (Downloaded == "no")
                        Update = "<span ><i class='installed fa fa-check-circle'></i> installed</span>";
                    else
                        Update = "<span><i class='uptodate fa fa-check'></i> up-to-date </span>";
                }else{
                    Update = "<span><i class='uninstalled fa fa-info-circle'></i> uninstalled </span>";
                }
            }else{
                Update = "<span ><a class='update'><i class='updateready fa fa-cloud-download'></i> update ready </a></span>";
                Ready = true;
            }

            if (DownloadEQ != Downloaded)
                Downloaded = 'old';

            var Checked = "";
            if (data.packages[i].config == "yes"){
                Checked = "checked";
                Count++;
           }

            $('#tblPackages tbody').append("<tr>"+
            "<td class='package' title='"+data.packages[i].desc+"'>"+data.packages[i].name+"</td>"+ // package name
            "<td>"+Update+"</td>"+ // package status
            "<td>"+data.packages[i].size+"</td>"+ // package size
            "<td>"+Downloaded+"</td>"+ // package downloaded
            "<td>"+Installed+"</td>"+ // package installed
            "<td>"+data.packages[i].plugins+"</td>"+ // package dependents
            "<td><input class='pkgcheckbox' id='"+data.packages[i].pkgname+"' type='checkbox' "+Checked+">"+
            "<input class='pkgvalue' type='hidden' id='"+data.packages[i].pkgname+"_value' name='"+data.packages[i].pkgnver+"' value='"+data.packages[i].config+"'></td>"+
            "</tr>");
        }
        if (Ready)
            $('#btnApply').prop('disabled', false);

        // attach switch buttons to every package checkbox all at once
        $('.pkgcheckbox')
            .switchButton({
                labels_placement: 'right',
                on_label: 'On',
                off_label: 'Off'
            })
            .change(function() {
                $(this).parent().parent().find('.pkgvalue').val(this.checked ? "yes": "no");
                $('#btnApply').prop("disabled", false);
            });

        // attach submit to update ready
        $('.update').click(Apply);

        // restore filters
        var lastSearch = $('#tblPackages')[0].config.lastSearch;
        $('#tblPackages').trigger('update')
        .trigger('search', [lastSearch]);

        if (data.empty == true && Count > 0) {
            swal({
                title:'Downloaded Packages Missing!',
                text:'You either changed unRAID versions or deleted your downloaded packages. Click Download or the Apply button below to download and install your selected packages.',
                type:'warning',
                showCancelButton: true,
                confirmButtonColor: "#00AA00",
                confirmButtonText: 'Download',
                closeOnConfirm: true,},
                function(isConfirm) {
                    $('#btnApply').prop('disabled', false);
                    if(isConfirm)
                        Apply();
                    else
                        $('html, body').animate({
                            scrollTop: $("#btnApply").offset().top
                        }, 2000);
                }
            );
        }
    });
}

function Apply() {
        $.post('/update.php', $('#package_form').serializeArray(), function() {
                openBox('/plugins/DevPack/scripts/devmanager&arg1=--download',
                            'Dev Package Manager', 600, 900, true);
            }
        );
}
