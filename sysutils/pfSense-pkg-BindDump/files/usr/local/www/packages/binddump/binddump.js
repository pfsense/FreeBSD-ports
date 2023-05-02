function reloadData() {
    leaselist.innerHTML = "<tr><td colspan=5>Loading...</td></tr>";

    $.ajax(
        {
            type: 'post',
            data: {
                loadData: 'true'
            },
            success: function (data) {
                leaselist.innerHTML = data;
                doSearch();
                refreshDropDown();
            },
            error: function (data) {
                $('#dlg_updatestatus_text').text(data.responseText);
                $('#dlg_updatestatus_text').attr('readonly', true);
                $('#dlg_updatestatus').modal('show');
            }
        });
}

function showMessage($text) {
    $('#dlg_updatestatus_text').text($text);
    $('#dlg_updatestatus_text').attr('readonly', true);
    $('#dlg_updatestatus').modal('show');
}
function showWait($text){
    $('#dlg_wait_text').text($text + '<br/><br/>Please wait for the process to complete.<br/><br/>This dialog will auto-close when the update is finished.<br/><br/>' +
    '<i class="content fa fa-spinner fa-pulse fa-lg text-center text-info"></i>');
    $('#dlg_wait_text').attr('readonly', true);
    $('#dlg_wait').modal('show');
}
function hideWait(){
    $('#dlg_wait').modal('hide');
}

function deleteHost(item) {
    if (confirm("Delete Host?")) {
        showWait('Deleting...');
        var dataitem = item;

        $.ajax(
            {
                type: 'post',
                data: {
                    action: "delete_host",
                    item: item
                },
                success: function (data) {
                    hideWait();

                    if (data == "[OK]") {
                        $("#leaselist tr[data-item='" + dataitem + "']").remove();
                        showMessage("Host deleted");
                    } else {
                        showMessage(data);
                    }
                },
                error: function (data) {
                    hideWait();
                    showMessage(data.responseText);
                }
            });
    }
}

function refreshDropDown() {
    $("#leaselist").find('tr').each(function (i) {
        var $tds = $(this).find('td'),
            zone = $(this).attr('data-zone'),
            type = $(this).attr('data-type');

        if ($('#zoneselect option[value="' + zone + '"]').length <= 0) {
            $("#zoneselect").append("<option value='" + zone + "'>" + zone + "</option>");
        }
        if ($('#typeselect option[value="' + type + '"]').length <= 0) {
            $("#typeselect").append("<option value='" + type + "'>" + type + "</option>");
        }
    });
}

function doSearch() {
    var searchstr = $('#searchstr').val().toLowerCase(),
        table = $("#leaselist"),
        where = $('#where').val(),
        zoneselect = $('#zoneselect').val(),
        typeselect = $('#typeselect').val();

    table.find('tr').each(function (i) {
        var $tds = $(this).find('td'),
            zone = $(this).attr('data-zone'),
            name = $(this).attr('data-name').toLowerCase(),
            type = $(this).attr('data-type'),
            rdata = $(this).attr('data-rdata').toLowerCase(),
            regexp = new RegExp(searchstr);

        if (searchstr.length > 0) {
            if ((!(regexp.test(zone.toLowerCase()) && ((where == 1) || (where == 0))) &&
                !(regexp.test(name) && ((where == 2) || (where == 0))) &&
                !(regexp.test(rdata) && ((where == 3) || (where == 0)))) ||
                !((type == typeselect) || (typeselect == "all")) ||
                !((zone == zoneselect) || (zoneselect == "all"))
            ) {
                $(this).hide();
            } else {
                $(this).show();
            }
        } else {
            if (!((zone == zoneselect) || (zoneselect == "all")) ||
                !((type == typeselect) || (typeselect == "all"))
            ) {
                $(this).hide();
            } else {
                $(this).show();
            }
        }
    });
}

// Function to download a file
async function downloadFile(url, filename) {
    showWait('Prepare download...');
    
    try {
        const response = await fetch(url);
        if (response.status !== 200) {
            hideWait();
            showMessage(`Unable to download file. HTTP status: ${response.status}`);
            exit;
        }

        const blob = await response.blob();
        const downloadLink = document.createElement('a');
        downloadLink.href = URL.createObjectURL(blob);
        downloadLink.download = filename;

        document.body.appendChild(downloadLink);
        downloadLink.click();

        // Clean up
        setTimeout(() => {
            URL.revokeObjectURL(downloadLink.href);
            document.body.removeChild(downloadLink);
        }, 100);

    } catch (error) {
        hideWait();
        showMessage('Error downloading the file: ' + error.message);
    } finally {
        hideWait();
    }
}

events.push(function () {
    // Make these controls plain buttons
    $("#btnsearch").prop('type', 'button');
    $("#btnclear").prop('type', 'button');
    $("#btnreload").prop('type', 'button');
    $("#btndownloadFullZone").prop('type', 'button');

    // $('#dlg_updatestatus').on('shown.bs.modal', function () {
    // });

    $("#btndownloadFullZone").click(function () {
        $("btndownloadFullZone").attr("disabled", true);

        downloadFile("binddump.php?download=zoneDump", "ZoneDump.txt")
            .then((response) => {
                $("btndownloadFullZone").attr("disabled", false);
                console.log("Received response: ${response.status}");
            });
    });

    $("#btnreload").click(reloadData);
    $("#btnsearch").click(doSearch);
    
    $("#btnclear").click(function () {
        $('#searchstr').val("");
        doSearch();
    });

    $("#searchstr").on("keyup", function (event) {
        if (event.keyCode == 13) {
            doSearch();
        }
    });

    $("#zoneselect").on("change", function (event) { doSearch(); });
    $("#typeselect").on("change", function (event) { doSearch(); });

    reloadData();
});