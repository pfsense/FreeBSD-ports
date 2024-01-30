/* global moment, $ */
/* exported CrowdSec */
/* eslint no-undef: "error" */
/* eslint semi: "error" */

const CrowdSec = (function () {
    'use strict';

    const api_url = '/crowdsec/endpoint/api.php';
    const crowdsec_path = '/usr/local/etc/crowdsec/';
    const _refreshTemplate = '<button class="btn btn-default" type="button" title="Refresh"><span class="icon fa fa-refresh"></span></button>';

    const _dataFormatters = {
        yesno: function (column, row) {
            return _yesno2html(row[column.id]);
        },

        delete: function (column, row) {
            var val = row.id;
            if (isNaN(val)) {
                return '';
            }
            return '<button type="button" class="btn btn-secondary btn-sm" value="' + val + '" onclick="CrowdSec.deleteDecision(' + val + ')"><i class="fa fa-trash" /></button>';
        },

        duration: function (column, row) {
            var duration = row[column.id];
            if (!duration) {
                return 'n/a';
            }
            return $('<div>').attr({
                'data-toggle': 'tooltip',
                'data-placement': 'left',
                title: duration
            }).text(_humanizeDuration(duration)).prop('outerHTML');
        },

        datetime: function (column, row) {
            var dt = row[column.id];
            var parsed = moment(dt);
            if (!dt) {
                return '';
            }
            if (!parsed.isValid()) {
                console.error('Cannot parse timestamp: %s', dt);
                return '???';
            }
            return $('<div>').attr({
                'data-toggle': 'tooltip',
                'data-placement': 'left',
                title: parsed.format()
            }).text(_humanizeDate(dt)).prop('outerHTML');
        }
    };
    let metricsInterval = null;

    function _decisionsByType(decisions) {
        const dectypes = {};
        if (!decisions) {
            return '';
        }
        decisions.map(function (decision) {
            // TODO ignore negative expiration?
            dectypes[decision.type] = dectypes[decision.type] ? (dectypes[decision.type] + 1) : 1;
        });
        let ret = '';
        for (const type in dectypes) {
            if (ret !== '') {
                ret += ' ';
            }
            ret += (type + ':' + dectypes[type]);
        }
        return ret;
    }

    function _updateFreshness(selector, timestamp) {
        var $freshness = $(selector).find('.actionBar .freshness');
        if (timestamp) {
            $freshness.data('refresh_timestamp', timestamp);
        } else {
            timestamp = $freshness.data('refresh_timestamp');
        }
        var howlongHuman = '???';
        if (timestamp) {
            var howlongms = moment() - moment(timestamp);
            howlongHuman = moment.duration(howlongms).humanize();
        }
        $freshness.text(howlongHuman + ' ago');
    }

    function _addFreshness(selector) {
        // this creates one timer per tab
        var freshnessTemplate = '<span style="float:left;"><i>Last refresh: <span class="freshness"></span></i></span>';
        $(selector).find('.actionBar').prepend(freshnessTemplate);
        setInterval(function () {
            _updateFreshness(selector);
        }, 5000);
    }

    function _refreshTab(selector, action, dataCallback) {
        $('.loading').show();
        $.ajax({
            url: api_url,
            cache: false,
            dataType: 'json',
            data: { action: action },
            type: 'POST',
            method: 'POST',
            success: dataCallback,
            complete: function () {
                $(".loading").hide();
                _updateFreshness(selector, moment());
            }
        })
    }

    function _parseDuration(duration) {
        var re = /(-?)(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+(m?)s/m;
        var matches = duration.match(re);
        var seconds = 0;

        if (!matches.length) {
            throw new Error('Unable to parse the following duration: ' + duration + '.');
        }
        if (typeof matches[2] !== 'undefined') {
            seconds += parseInt(matches[2], 10) * 3600; // hours
        }
        if (typeof matches[3] !== 'undefined') {
            seconds += parseInt(matches[3], 10) * 60; // minutes
        }
        if (typeof matches[4] !== 'undefined') {
            seconds += parseInt(matches[4], 10); // seconds
        }
        if (parseInt(matches[5], 10) === 'm') {
            // units in milliseconds
            seconds *= 0.001;
        }
        if (parseInt(matches[1], 10) === '-') {
            // negative
            seconds = -seconds;
        }
        return seconds;
    }

    function _humanizeDate(text) {
        return moment(text).fromNow();
    }

    function _humanizeDuration(text) {
        return moment.duration(_parseDuration(text), 'seconds').humanize();
    }

    function _yesno2html(val) {
        if (val) {
            return '<i class="fa fa-check text-success"></i>';
        } else {
            return '<i class="fa fa-times text-danger"></i>';
        }
    }

    function _initTab(selector, action, dataCallback) {
        const tab = $(selector);
        const table = tab.find('table.crowdsecTable');
        if (!table.length) {
            return;
        }
        // Navigation
        window.location.hash = selector;
        history.pushState(null, null, window.location.hash);
        table.on('initialized.rs.jquery.bootgrid', function () {
            $(_refreshTemplate).on('click', function () {
                _refreshTab(selector, action, dataCallback);
            }).insertBefore(tab.find('.actionBar .actions .dropdown:first'));
            _addFreshness(selector);
            _refreshTab(selector, action, dataCallback);
            if (action.startsWith("metrics")) {
                // Refresh periodically
                if (metricsInterval) {
                    clearInterval(metricsInterval);
                }
                metricsInterval = setInterval(function () {
                    _refreshTab(selector, action, dataCallback)
                }, 60000);
            }
        }).bootgrid({
            rowCount: [50, 100, 200],
            caseSensitive: false,
            formatters: _dataFormatters
        })
    }

    function _initStatusMachines() {
        const action = 'status-machines-list';
        const id = '#tab-status-machines';
        const dataCallback = function (data) {
            const rows = [];
            data.map(function (row) {
                rows.push({
                    name: row.machineId,
                    ip_address: row.ipAddress || ' ',
                    last_update: row.updated_at || ' ',
                    validated: row.isValidated,
                    version: row.version || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusCollections() {
        const action = 'status-collections-list';
        const id = "#tab-status-collections";
        const dataCallback = function (data) {
            const rows = [];
            if (data.collections) {
                data.collections.map(function (row) {
                    rows.push({
                        name: row.name,
                        status: row.status,
                        local_version: row.local_version || ' ',
                        local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                        description: row.description || ' '
                    });
                });
            }
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusScenarios() {
        const action = 'status-scenarios-list';
        const id = "#tab-status-scenarios";
        const dataCallback = function (data) {
            const rows = [];
            data.scenarios.map(function (row) {
                rows.push({
                    name: row.name,
                    status: row.status,
                    local_version: row.local_version || ' ',
                    local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                    description: row.description || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusAppsecConfigs() {
        const action = 'status-appsec-configs-list';
        const id = "#tab-status-appsec-configs";
        const dataCallback = function (data) {
            const rows = [];
            data["appsec-configs"].map(function (row) {
                rows.push({
                    name: row.name,
                    status: row.status,
                    local_version: row.local_version || ' ',
                    local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                    description: row.description || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusAppsecRules() {
        const action = 'status-appsec-rules-list';
        const id = "#tab-status-appsec-rules";
        const dataCallback = function (data) {
            const rows = [];
            data["appsec-rules"].map(function (row) {
                rows.push({
                    name: row.name,
                    status: row.status,
                    local_version: row.local_version || ' ',
                    local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                    description: row.description || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusContexts() {
        const action = 'status-contexts-list';
        const id = "#tab-status-contexts";
        const dataCallback = function (data) {
            const rows = [];
            data.contexts.map(function (row) {
                rows.push({
                    name: row.name,
                    status: row.status,
                    local_version: row.local_version || ' ',
                    local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                    description: row.description || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusParsers() {
        const action = 'status-parsers-list';
        const id = "#tab-status-parsers";
        const dataCallback = function (data) {
            const rows = [];
            data.parsers.map(function (row) {
                rows.push({
                    name: row.name,
                    status: row.status,
                    local_version: row.local_version || ' ',
                    local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                    description: row.description || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusPostoverflows() {
        const action = 'status-postoverflows-list';
        const id = "#tab-status-postoverflows";
        const dataCallback = function (data) {
            const rows = [];
            data.postoverflows.map(function (row) {
                rows.push({
                    name: row.name,
                    status: row.status,
                    local_version: row.local_version || ' ',
                    local_path: row.local_path ? row.local_path.replace(crowdsec_path, '') : ' ',
                    description: row.description || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusBouncers() {
        const action = 'status-bouncers-list';
        const id = "#tab-status-bouncers";
        const dataCallback = function (data) {
            const rows = [];
            data.map(function (row) {
                // TODO - remove || ' ' later, it was fixed for 1.3.3
                rows.push({
                    name: row.name,
                    ip_address: row.ip_address || ' ',
                    valid: !row.revoked,
                    last_pull: row.last_pull,
                    type: row.type || ' ',
                    version: row.version || ' '
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusAlerts() {
        const action = 'status-alerts-list';
        const id = "#tab-status-alerts";
        const dataCallback = function (data) {
            const rows = [];
            data.map(function (row) {
                rows.push({
                    id: row.id,
                    value: row.source.scope + (row.source.value ? (':' + row.source.value) : ''),
                    reason: row.scenario || ' ',
                    country: row.source.cn || ' ',
                    as: row.source.as_name || ' ',
                    decisions: _decisionsByType(row.decisions) || ' ',
                    created_at: row.created_at
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initStatusDecisions() {
        const action = 'status-decisions-list';
        const id = "#tab-status-decisions";
        const dataCallback = function (data) {
            const rows = [];
            data.map(function (row) {
                row.decisions.map(function (decision) {
                    // ignore deleted decisions
                    if (decision.duration.startsWith('-')) {
                        return;
                    }
                    rows.push({
                        // search will break on empty values when using .append(). so we use spaces
                        delete: '',
                        id: decision.id,
                        source: decision.origin || ' ',
                        scope_value: decision.scope + (decision.value ? (':' + decision.value) : ''),
                        reason: decision.scenario || ' ',
                        action: decision.type || ' ',
                        country: row.source.cn || ' ',
                        as: row.source.as_name || ' ',
                        events_count: row.events_count,
                        // XXX pre-parse duration to seconds, and integer type, for sorting
                        expiration: decision.duration || ' ',
                        alert_id: row.id || ' '
                    });
                });
            });
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsAcquisition() {
        const action = 'metrics-acquisition-list';
        const id = "#tab-metrics-acquisition";
        const dataCallback = function (data) {
            const rows = [];
            if (data.acquisition) {
                const acquisition = Object.entries(data.acquisition);
                acquisition.map(function (acquisition) {
                    if (acquisition.length === 2) {
                        rows.push({
                            // search will break on empty values when using .append(). so we use spaces
                            source: acquisition[0] || ' ',
                            read: acquisition[1].reads || ' ',
                            parsed: acquisition[1].parsed || ' ',
                            unparsed: acquisition[1].unparsed || ' ',
                            poured: acquisition[1].pour || ' ',
                        });
                    }
                });
            }
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsDecisions() {
        const action = 'metrics-decisions-list';
        const id = "#tab-metrics-decisions";
        const dataCallback = function (data) {
            const rows = [];
            if (data.decisions) {
                const decisions = Object.entries(data.decisions);
                decisions.map(function (decision) {
                    if (decision.length === 2) {
                        const origins = Object.entries(decision[1]);
                        origins.map(function (origin) {
                            if (origin.length === 2) {
                                const types = Object.entries(origin[1]);
                                types.map(function (type) {
                                    if (type.length === 2) {
                                        rows.push({
                                            // search will break on empty values when using .append(). so we use spaces
                                            reason: decision[0] || ' ',
                                            origin: origin[0],
                                            action: type[0],
                                            count: type[1]
                                        });
                                    }
                                });
                            }
                        });
                    }
                });
            }
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsBucket() {
        const action = 'metrics-bucket-list';
        const id = "#tab-metrics-bucket";
        const dataCallback = function (data) {
            const rows = [];
            if (data.buckets) {
                const buckets = Object.entries(data.buckets);
                buckets.map(function (bucket) {
                    if (bucket.length === 2) {
                        rows.push({
                            bucket: bucket[0] || ' ',
                            current: bucket[1].curr_count || ' ',
                            overflows: bucket[1].overflow || ' ',
                            instantiated: bucket[1].instantiation || ' ',
                            poured: bucket[1].pour || ' ',
                            underflows: bucket[1].underflow || ' ',
                        });
                    }
                });
            }
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsParser() {
        const action = 'metrics-parser-list';
        const id = "#tab-metrics-parser";
        const dataCallback = function (data) {
            const rows = [];
            if (data.parsers) {
                const parsers = Object.entries(data.parsers);
                parsers.map(function (parser) {
                    if (parser.length === 2) {
                        rows.push({
                            parsers: parser[0] || ' ',
                            hits: parser[1].hits || ' ',
                            parsed: parser[1].parsed || ' ',
                            unparsed: parser[1].unparsed || ' '
                        });
                    }
                });
            }

            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsAlerts() {
        const action = 'metrics-alerts-list';
        const id = "#tab-metrics-alerts";
        const dataCallback = function (data) {
            const rows = [];
            if (data.alerts) {
                const alerts = Object.entries(data.alerts);
                alerts.map(function (alert) {
                    if (alert.length === 2) {
                        rows.push({
                            reason: alert[0] || ' ',
                            count: alert[1] || ' '
                        });
                    }
                });
            }
            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsLapiMachines() {
        const action = 'metrics-lapi-machines-list';
        const id = "#tab-metrics-lapi-machines";
        const dataCallback = function (data) {
            const rows = [];
            if (data.lapi_machine) {
                const machines = Object.entries(data.lapi_machine);
                machines.map(function (machine) {
                    if (machine.length === 2) {
                        const routes = Object.entries(machine[1]);
                        routes.map(function (route) {
                            const methods = Object.values(route);
                            if (methods.length === 2) {
                                const methodTypes = Object.entries(methods[1]);
                                methodTypes.map(function (type) {
                                    if (type.length === 2) {
                                        rows.push({
                                            machine: machine[0] || ' ',
                                            route: route[0] || ' ',
                                            method: type[0],
                                            hits: type[1]
                                        });
                                    }
                                });

                            }
                        });
                    }
                });
            }

            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function _initMetricsLapiBouncers() {
        const action = 'metrics-lapi-bouncers-list';
        const id = "#tab-metrics-lapi-bouncers";
        const dataCallback = function (data) {
            const rows = [];
            if (data.lapi_bouncer) {
                const bouncers = Object.entries(data.lapi_bouncer);
                bouncers.map(function (bouncer) {
                    if (bouncer.length === 2) {
                        const routes = Object.entries(bouncer[1]);
                        routes.map(function (route) {
                            const methods = Object.values(route);
                            if (methods.length === 2) {
                                const methodTypes = Object.entries(methods[1]);
                                methodTypes.map(function (type) {
                                    if (type.length === 2) {
                                        rows.push({
                                            bouncer: bouncer[0] || ' ',
                                            route: route[0] || ' ',
                                            method: type[0],
                                            hits: type[1]
                                        });
                                    }
                                });

                            }
                        });
                    }
                });
            }

            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }


    function _initMetricsLapi() {
        const action = 'metrics-lapi-list';
        const id = "#tab-metrics-lapi";
        const dataCallback = function (data) {
            const rows = [];
            if (data.lapi) {
                const infos = Object.entries(data.lapi);
                infos.map(function (info) {
                    if (info.length === 2) {
                        const routes = Object.entries(info[1]);
                        routes.map(function (route) {
                            if (route.length === 2) {
                                rows.push({
                                    route: info[0] || ' ',
                                    method: route[0],
                                    hits: route[1]
                                });
                            }
                        });
                    }
                });
            }

            $(id + ' table').bootgrid('clear').bootgrid('append', rows);
        };
        _initTab(id, action, dataCallback);
    }

    function initService() {
        $.ajax({
            url: api_url,
            cache: false,
            dataType: 'json',
            data: { action: 'services-status' },
            type: 'POST',
            method: 'POST',
            success: function (data) {
                var crowdsecStatus = data['crowdsec-status'];
                if (crowdsecStatus === 'unknown') {
                    crowdsecStatus = '<span class="text-danger">Unknown</span>';
                } else {
                    crowdsecStatus = _yesno2html(crowdsecStatus === 'running');
                }
                $('#crowdsec-status').html(crowdsecStatus);

                var crowdsecFirewallStatus = data['crowdsec-firewall-status'];
                if (crowdsecFirewallStatus === 'unknown') {
                    crowdsecFirewallStatus = '<span class="text-danger">Unknown</span>';
                } else {
                    crowdsecFirewallStatus = _yesno2html(crowdsecFirewallStatus === 'running');
                }
                $('#crowdsec-firewall-status').html(crowdsecFirewallStatus);
            }
        })
    }

    function deleteDecision(decisionId) {
        const $modal = $('#remove-decision-modal');
        const action = 'status-decision-delete';

        $modal.find('.modal-title').text('Delete decision #' + decisionId);
        $modal.find('.modal-body').text('Are you sure?');
        $modal.modal('show');
        $modal.find('#remove-decision-confirm').on('click', function () {
            $.ajax({
                // XXX handle errors
                url: api_url + '?action=' + action + '&decision_id=' + decisionId,
                type: 'DELETE',
                method: 'DELETE',
                dataType: 'json',
                success: function (result) {
                    if (result && result.message === 'OK') {
                        $('#tab-status-decisions table').bootgrid('remove', [decisionId]);
                    }
                }
            });
        });
    }

    function _handleStatusHash(hash) {
        $('#hub-dropdown li').each(function () {
            if ($(this).data('tab') === hash.replace('#', '')) {
                $(this).addClass('active');
                $('#hub-dropdown-parent').addClass('ui-tabs-active ui-state-active');
            }
        });
        switch (hash) {
            case '#tab-status-alerts':
                _initStatusAlerts();
                break;
            case '#tab-status-bouncers':
                _initStatusBouncers();
                break;
            case '#tab-status-collections':
                _initStatusCollections();
                break;
            case '#tab-status-decisions':
                _initStatusDecisions();
                break;
            case '#tab-status-machines':
                _initStatusMachines();
                break;
            case '#tab-status-parsers':
                _initStatusParsers();
                break;
            case '#tab-status-postoverflows':
                _initStatusPostoverflows();
                break;
            case '#tab-status-scenarios':
                _initStatusScenarios();
                break;
            case '#tab-status-appsec-configs':
                _initStatusAppsecConfigs();
                break;
            case '#tab-status-appsec-rules':
                _initStatusAppsecRules();
                break;
            case '#tab-status-contexts':
                _initStatusContexts();
                break;
            default:
                // First tab is collection for remote lapi
                if ($('#li-status-machines').length === 0) {
                    _initStatusCollections();
                } else {
                    _initStatusMachines();
                }
        }
    }

    function _handleMetricsHash(hash) {
        switch (hash) {
            case '#tab-metrics-acquisition':
                _initMetricsAcquisition();
                break;
            case '#tab-metrics-bucket':
                _initMetricsBucket();
                break;
            case '#tab-metrics-parser':
                _initMetricsParser();
                break;
            case '#tab-metrics-lapi':
                _initMetricsLapi();
                break;
            case '#tab-metrics-lapi-machines':
                _initMetricsLapiMachines();
                break;
            case '#tab-metrics-lapi-bouncers':
                _initMetricsLapiBouncers();
                break;
            case '#tab-metrics-decisions':
                _initMetricsDecisions();
                break;
            case '#tab-metrics-alerts':
                _initMetricsAlerts();
                break;
            default:
                _initMetricsAcquisition();

        }
    }

    function initStatus() {
        // Machines tab is the first to be visible
        $("#tabs").tabs({
            beforeActivate: function (event, ui) {
                switch (ui.newPanel[0].id) {
                    case 'hub-tabs':
                        event.preventDefault();
                        break;
                    default:
                        break
                }
            },
            activate: function (event, ui) {
                switch (ui.newPanel[0].id) {
                    case 'tab-status-alerts':
                        _initStatusAlerts();
                        break;
                    case 'tab-status-bouncers':
                        _initStatusBouncers();
                        break;
                    case 'tab-status-decisions':
                        _initStatusDecisions();
                        break;
                    case 'tab-status-machines':
                        _initStatusMachines();
                        break;
                    case 'tab-status-collections':
                        _initStatusCollections();
                        break;
                    case 'tab-status-parsers':
                        _initStatusParsers();
                        break;
                    case 'tab-status-postoverflows':
                        _initStatusPostoverflows();
                        break;
                    case 'tab-status-scenarios':
                        _initStatusScenarios();
                        break;
                    case 'tab-status-appsec-configs':
                        _initStatusAppsecConfigs();
                        break;
                    case 'tab-status-appsec-rules':
                        _initStatusAppsecRules();
                        break;
                    case 'tab-status-contexts':
                        _initStatusContexts();
                        break;
                    default:
                        _initStatusMachines();
                        break
                }
            }
        });
        // activate a tab from the hash, if it exists
        _handleStatusHash(window.location.hash);

        $(window).on('hashchange', function (e) {
            _handleStatusHash(window.location.hash);
        });

        $(window).on('popstate', function (event) {
            _handleStatusHash(window.location.hash);
        });

        // Handle Hub tab
        $("#hub-dropdown-parent").mouseenter(function () {
            $("#hub-dropdown").show();
        }).mouseleave(function () {
            $("#hub-dropdown").hide();
        });
        $('#tabs li').on('click', function () {
            const parent = $(this).parent('ul');
            if ($(this).hasClass("main-tab")) {
                $('#hub-dropdown-parent').removeClass('ui-tabs-active ui-state-active');
                $('#hub-dropdown li').removeClass('active');
            }
        });
        $('#hub-dropdown li').on('click', function () {
            const dataTabValue = $(this).data('tab');
            const $targetLink = $("li.hub a").filter(function () {
                return $(this).attr('href') === `#${dataTabValue}`;
            });
            if ($targetLink.length) {
                $targetLink.click();
            }
            $('#hub-dropdown-parent').mouseleave();
            $('#tabs li').removeClass('ui-tabs-active ui-state-active');
            $('#hub-dropdown-parent').addClass('ui-tabs-active ui-state-active');
            $('#hub-dropdown li').removeClass('active');
            $(this).addClass('active');
        });
    }

    function initMetrics() {
        // Acquisition tab is the first to be visible
        $("#tabs").tabs({
            activate: function (event, ui) {
                switch (ui.newPanel[0].id) {
                    case 'tab-metrics-acquisition':
                        _initMetricsAcquisition();
                        break;
                    case 'tab-metrics-bucket':
                        _initMetricsBucket();
                        break;
                    case 'tab-metrics-parser':
                        _initMetricsParser();
                        break;
                    case 'tab-metrics-lapi':
                        _initMetricsLapi();
                        break;
                    case 'tab-metrics-lapi-machines':
                        _initMetricsLapiMachines();
                        break;
                    case 'tab-metrics-lapi-bouncers':
                        _initMetricsLapiBouncers();
                        break;
                    case 'tab-metrics-decisions':
                        _initMetricsDecisions();
                        break;
                    case 'tab-metrics-alerts':
                        _initMetricsAlerts();
                        break;
                    default:
                        _initMetricsAcquisition();
                        break
                }
            }
        });
        // activate a tab from the hash, if it exists
        _handleMetricsHash(window.location.hash);

        $(window).on('hashchange', function (e) {
            _handleMetricsHash(window.location.hash);
        });

        $(window).on('popstate', function (event) {
            _handleMetricsHash(window.location.hash);
        });
    }

    return {
        deleteDecision: deleteDecision,
        initStatus: initStatus,
        initMetrics: initMetrics,
        initService: initService
    };
}());
