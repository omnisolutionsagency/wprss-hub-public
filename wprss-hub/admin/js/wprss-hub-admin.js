/**
 * WPRSS Hub Admin JS — vanilla JS, no build step.
 * Uses wprssHub.restUrl and wprssHub.nonce from wp_localize_script.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    function api(endpoint, opts) {
        opts = opts || {};
        var url = wprssHub.restUrl + endpoint;
        var options = {
            method: opts.method || 'GET',
            headers: {
                'X-WP-Nonce': wprssHub.nonce,
                'Content-Type': 'application/json',
            },
        };
        if (opts.body) {
            options.body = JSON.stringify(opts.body);
        }
        return fetch(url, options).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (err) {
                    throw new Error(err.message || 'Request failed');
                });
            }
            return res.json();
        });
    }

    function toast(message, type) {
        var el = document.createElement('div');
        el.className = 'wprss-hub-toast wprss-hub-toast--' + (type || 'pass');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.classList.add('wprss-hub-toast--visible'); }, 10);
        setTimeout(function () {
            el.classList.remove('wprss-hub-toast--visible');
            setTimeout(function () { el.remove(); }, 300);
        }, 4000);
    }

    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function badge(status) {
        return '<span class="wprss-hub-badge wprss-hub-badge--' + esc(status) + '">' + esc(status) + '</span>';
    }

    function timeAgo(timestamp) {
        if (!timestamp) return '—';
        var diff = Math.floor(Date.now() / 1000) - parseInt(timestamp, 10);
        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    /* Cached site list for name lookups */
    var sitesCache = null;

    function loadSites() {
        if (sitesCache) return Promise.resolve(sitesCache);
        return api('sites').then(function (sites) {
            sitesCache = sites;
            return sites;
        });
    }

    function siteName(id) {
        if (!sitesCache) return '#' + id;
        for (var i = 0; i < sitesCache.length; i++) {
            if (parseInt(sitesCache[i].id) === parseInt(id)) return sitesCache[i].name;
        }
        return '#' + id;
    }

    /* ------------------------------------------------------------------ */
    /*  Sites Page                                                         */
    /* ------------------------------------------------------------------ */

    function initSitesPage() {
        var table = document.getElementById('wprss-hub-sites-table');
        if (!table) return;

        var tbody = table.querySelector('tbody');
        var formWrap = document.getElementById('wprss-hub-site-form-wrap');
        var addBtn = document.getElementById('wprss-hub-add-site-btn');
        var saveBtn = document.getElementById('wprss-hub-save-site');
        var cancelBtn = document.getElementById('wprss-hub-cancel-site');

        function renderSites(sites) {
            sitesCache = sites;
            if (!sites.length) {
                tbody.innerHTML = '<tr><td colspan="5">' + esc(wprssHub.i18n.loading).replace(wprssHub.i18n.loading, 'No sites registered yet.') + '</td></tr>';
                return;
            }
            tbody.innerHTML = sites.map(function (s) {
                var sshStatus = s.ssh_host ? 'Yes' : 'No';
                return '<tr data-id="' + s.id + '">' +
                    '<td>' + esc(s.name) + '</td>' +
                    '<td><a href="' + esc(s.site_url) + '" target="_blank">' + esc(s.site_url) + '</a></td>' +
                    '<td>' + badge(s.status) + '</td>' +
                    '<td>' + esc(sshStatus) + '</td>' +
                    '<td>' +
                        '<button class="button button-small wprss-hub-edit-site">' + 'Edit' + '</button> ' +
                        '<button class="button button-small wprss-hub-test-site">' + 'Test' + '</button> ' +
                        '<button class="button button-small wprss-hub-toggle-site">' + (s.status === 'active' ? 'Disable' : 'Enable') + '</button> ' +
                        '<button class="button button-small wprss-hub-delete-site">' + 'Delete' + '</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
        }

        function loadAndRender() {
            api('sites').then(renderSites).catch(function () { toast('Failed to load sites.', 'fail'); });
        }

        addBtn.addEventListener('click', function () {
            document.getElementById('site-edit-id').value = '';
            document.getElementById('wprss-hub-site-form-title').textContent = 'Add Site';
            ['site-name','site-url','site-app-user','site-app-password','site-ssh-host','site-ssh-user','site-ssh-key-path','site-wp-path'].forEach(function(id) {
                document.getElementById(id).value = '';
            });
            document.getElementById('site-ssh-port').value = '22';
            formWrap.style.display = '';
            addBtn.style.display = 'none';
        });

        cancelBtn.addEventListener('click', function () {
            formWrap.style.display = 'none';
            addBtn.style.display = '';
        });

        saveBtn.addEventListener('click', function () {
            var editId = document.getElementById('site-edit-id').value;
            var data = {
                name: document.getElementById('site-name').value,
                site_url: document.getElementById('site-url').value,
                app_user: document.getElementById('site-app-user').value,
                app_password: document.getElementById('site-app-password').value,
                ssh_host: document.getElementById('site-ssh-host').value,
                ssh_port: parseInt(document.getElementById('site-ssh-port').value) || 22,
                ssh_user: document.getElementById('site-ssh-user').value,
                ssh_key_path: document.getElementById('site-ssh-key-path').value,
                wp_path: document.getElementById('site-wp-path').value,
            };

            var method = editId ? 'PUT' : 'POST';
            var endpoint = editId ? 'sites/' + editId : 'sites';

            api(endpoint, { method: method, body: data }).then(function () {
                toast('Site saved!', 'pass');
                formWrap.style.display = 'none';
                addBtn.style.display = '';
                loadAndRender();
            }).catch(function (e) { toast(e.message, 'fail'); });
        });

        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var row = btn.closest('tr');
            var id = row.dataset.id;

            if (btn.classList.contains('wprss-hub-edit-site')) {
                api('sites').then(function (sites) {
                    var site = sites.find(function (s) { return String(s.id) === id; });
                    if (!site) return;
                    document.getElementById('site-edit-id').value = id;
                    document.getElementById('wprss-hub-site-form-title').textContent = 'Edit Site';
                    document.getElementById('site-name').value = site.name;
                    document.getElementById('site-url').value = site.site_url;
                    document.getElementById('site-app-user').value = site.app_user;
                    document.getElementById('site-app-password').value = '';
                    document.getElementById('site-ssh-host').value = site.ssh_host || '';
                    document.getElementById('site-ssh-port').value = site.ssh_port || 22;
                    document.getElementById('site-ssh-user').value = site.ssh_user || '';
                    document.getElementById('site-ssh-key-path').value = site.ssh_key_path || '';
                    document.getElementById('site-wp-path').value = site.wp_path || '';
                    formWrap.style.display = '';
                    addBtn.style.display = 'none';
                });
            }

            if (btn.classList.contains('wprss-hub-test-site')) {
                var modal = document.getElementById('wprss-hub-test-modal');
                var results = document.getElementById('wprss-hub-test-results');
                results.innerHTML = '<p class="wprss-hub-spinner">' + esc(wprssHub.i18n.testing) + '</p>';
                modal.style.display = '';
                api('sites/' + id + '/test', { method: 'POST' }).then(function (data) {
                    var html = '';
                    for (var method in data) {
                        html += '<p>' + esc(method.toUpperCase()) + ': ' + badge(data[method].status) + ' ' + esc(data[method].message) + '</p>';
                    }
                    results.innerHTML = html;
                }).catch(function (e) {
                    results.innerHTML = '<p>' + badge('fail') + ' ' + esc(e.message) + '</p>';
                });
            }

            if (btn.classList.contains('wprss-hub-toggle-site')) {
                var newStatus = btn.textContent.trim() === 'Disable' ? 'disabled' : 'active';
                api('sites/' + id, { method: 'PUT', body: { status: newStatus } }).then(function () {
                    toast('Site ' + newStatus + '.', 'pass');
                    loadAndRender();
                }).catch(function (e) { toast(e.message, 'fail'); });
            }

            if (btn.classList.contains('wprss-hub-delete-site')) {
                if (!confirm(wprssHub.i18n.confirm_delete)) return;
                api('sites/' + id, { method: 'DELETE' }).then(function () {
                    toast('Site deleted.', 'pass');
                    loadAndRender();
                }).catch(function (e) { toast(e.message, 'fail'); });
            }
        });

        // Modal close.
        document.querySelectorAll('.wprss-hub-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.wprss-hub-modal').style.display = 'none';
            });
        });

        loadAndRender();
    }

    /* ------------------------------------------------------------------ */
    /*  Feeds Page                                                         */
    /* ------------------------------------------------------------------ */

    function initFeedsPage() {
        var feedsTable = document.getElementById('wprss-hub-feeds-table');
        if (!feedsTable) return;

        var tbody = feedsTable.querySelector('tbody');

        // Tab switching.
        document.querySelectorAll('#wprss-hub-feed-tabs .nav-tab').forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('#wprss-hub-feed-tabs .nav-tab').forEach(function (t) { t.classList.remove('nav-tab-active'); });
                tab.classList.add('nav-tab-active');
                document.querySelectorAll('.wprss-hub-tab-content').forEach(function (c) { c.style.display = 'none'; });
                document.getElementById('wprss-hub-tab-' + tab.dataset.tab).style.display = '';
            });
        });

        function renderFeeds(feeds) {
            if (!feeds.length) {
                tbody.innerHTML = '<tr><td colspan="4">No feeds yet.</td></tr>';
                return;
            }
            tbody.innerHTML = feeds.map(function (f) {
                var assigned = [];
                try { assigned = JSON.parse(f.assigned_sites); } catch (e) {}
                var siteNames = assigned.map(function (sid) { return esc(siteName(sid)); }).join(', ') || '—';
                return '<tr data-id="' + f.id + '">' +
                    '<td>' + esc(f.feed_title || '(untitled)') + '</td>' +
                    '<td>' + esc(f.feed_url) + '</td>' +
                    '<td><span class="wprss-hub-assigned-sites">' + siteNames + '</span> <button class="button button-small wprss-hub-assign-feed">Edit</button></td>' +
                    '<td><button class="button button-small wprss-hub-delete-feed">Delete</button></td>' +
                '</tr>';
            }).join('');
        }

        function loadAndRender() {
            loadSites().then(function () {
                return api('feeds');
            }).then(renderFeeds).catch(function () { toast('Failed to load feeds.', 'fail'); });
        }

        // Populate site checkboxes for Add tab and Mirror tab.
        loadSites().then(function (sites) {
            var checksHtml = sites.map(function (s) {
                return '<label><input type="checkbox" name="feed_sites" value="' + s.id + '"> ' + esc(s.name) + '</label><br>';
            }).join('');
            var feedSitesDiv = document.getElementById('wprss-hub-feed-sites-checkboxes');
            if (feedSitesDiv) feedSitesDiv.innerHTML = checksHtml || '<p>No sites.</p>';

            // Mirror source dropdown.
            var sourceSelect = document.getElementById('mirror-source');
            if (sourceSelect) {
                sourceSelect.innerHTML = '<option value="">' + '— Select —' + '</option>' +
                    sites.map(function (s) { return '<option value="' + s.id + '">' + esc(s.name) + '</option>'; }).join('');
            }

            // Mirror target checkboxes.
            var mirrorTargets = document.getElementById('wprss-hub-mirror-targets');
            if (mirrorTargets) mirrorTargets.innerHTML = checksHtml || '<p>No sites.</p>';
        });

        // Save feed.
        var saveFeedBtn = document.getElementById('wprss-hub-save-feed');
        if (saveFeedBtn) {
            saveFeedBtn.addEventListener('click', function () {
                var checkedSites = [];
                document.querySelectorAll('#wprss-hub-feed-sites-checkboxes input:checked').forEach(function (cb) {
                    checkedSites.push(parseInt(cb.value));
                });
                api('feeds', {
                    method: 'POST',
                    body: {
                        feed_url: document.getElementById('feed-url').value,
                        feed_title: document.getElementById('feed-title').value,
                        assigned_sites: checkedSites,
                        notes: document.getElementById('feed-notes').value,
                    }
                }).then(function () {
                    toast('Feed added!', 'pass');
                    document.getElementById('feed-url').value = '';
                    document.getElementById('feed-title').value = '';
                    document.getElementById('feed-notes').value = '';
                    document.querySelectorAll('#wprss-hub-feed-sites-checkboxes input:checked').forEach(function (cb) { cb.checked = false; });
                    loadAndRender();
                }).catch(function (e) { toast(e.message, 'fail'); });
            });
        }

        // Mirror feeds.
        var mirrorBtn = document.getElementById('wprss-hub-mirror-btn');
        if (mirrorBtn) {
            mirrorBtn.addEventListener('click', function () {
                var source = document.getElementById('mirror-source').value;
                var targets = [];
                document.querySelectorAll('#wprss-hub-mirror-targets input:checked').forEach(function (cb) {
                    targets.push(parseInt(cb.value));
                });
                if (!source || !targets.length) {
                    toast('Select a source and at least one target.', 'fail');
                    return;
                }
                api('feeds/mirror', {
                    method: 'POST',
                    body: { source_site_id: parseInt(source), target_site_ids: targets }
                }).then(function () {
                    toast('Mirror jobs queued!', 'pass');
                }).catch(function (e) { toast(e.message, 'fail'); });
            });
        }

        // Delete feed and assign feed handlers.
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var row = btn.closest('tr');
            var id = row.dataset.id;

            if (btn.classList.contains('wprss-hub-delete-feed')) {
                if (!confirm(wprssHub.i18n.confirm_delete)) return;
                api('feeds/' + id, { method: 'DELETE' }).then(function () {
                    toast('Feed deleted.', 'pass');
                    loadAndRender();
                }).catch(function (e) { toast(e.message, 'fail'); });
            }

            if (btn.classList.contains('wprss-hub-assign-feed')) {
                document.getElementById('assign-feed-id').value = id;
                var modal = document.getElementById('wprss-hub-assign-modal');
                var checksDiv = document.getElementById('wprss-hub-assign-checkboxes');

                // Load current assignments.
                api('feeds').then(function (feeds) {
                    var feed = feeds.find(function (f) { return String(f.id) === id; });
                    var assigned = [];
                    try { assigned = JSON.parse(feed.assigned_sites); } catch (e) {}

                    checksDiv.innerHTML = (sitesCache || []).map(function (s) {
                        var checked = assigned.indexOf(parseInt(s.id)) > -1 ? ' checked' : '';
                        return '<label><input type="checkbox" name="assign_sites" value="' + s.id + '"' + checked + '> ' + esc(s.name) + '</label><br>';
                    }).join('') || '<p>No sites.</p>';

                    modal.style.display = '';
                });
            }
        });

        // Save assignments.
        var saveAssignBtn = document.getElementById('wprss-hub-save-assignments');
        if (saveAssignBtn) {
            saveAssignBtn.addEventListener('click', function () {
                var feedId = document.getElementById('assign-feed-id').value;
                var checked = [];
                document.querySelectorAll('#wprss-hub-assign-checkboxes input:checked').forEach(function (cb) {
                    checked.push(parseInt(cb.value));
                });
                api('feeds/' + feedId, { method: 'PUT', body: { assigned_sites: checked } }).then(function () {
                    toast('Assignments saved!', 'pass');
                    document.getElementById('wprss-hub-assign-modal').style.display = 'none';
                    loadAndRender();
                }).catch(function (e) { toast(e.message, 'fail'); });
            });
        }

        // Modal close.
        document.querySelectorAll('.wprss-hub-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.wprss-hub-modal').style.display = 'none';
            });
        });

        loadAndRender();
    }

    /* ------------------------------------------------------------------ */
    /*  Settings Page                                                      */
    /* ------------------------------------------------------------------ */

    function initSettingsPage() {
        var grid = document.getElementById('wprss-hub-settings-grid');
        if (!grid) return;

        var thead = grid.querySelector('thead tr');
        var tbody = document.getElementById('wprss-hub-settings-body');

        var settingKeys = [
            { key: 'cron_interval', label: 'Import interval' },
            { key: 'limit_feed_items_enabled', label: 'Limit items per feed' },
            { key: 'limit_feed_items_number', label: 'Max items per feed' },
            { key: 'feed_request_useragent', label: 'Request user-agent' },
            { key: 'delete_on_feed_delete', label: 'Delete items when feed deleted' },
            { key: 'source_link', label: 'Link items to source' },
            { key: 'open_dd', label: 'Open links in new tab' },
            { key: 'follow_feed_items_url', label: 'Follow canonical URL' },
        ];

        loadSites().then(function (sites) {
            var activeSites = sites.filter(function (s) { return s.status === 'active'; });

            // Add site columns to thead.
            activeSites.forEach(function (s) {
                var th = document.createElement('th');
                th.textContent = s.name;
                thead.appendChild(th);
            });

            // Build rows with skeleton cells.
            tbody.innerHTML = settingKeys.map(function (sk) {
                var cells = '<td class="wprss-hub-sticky-col">' + esc(sk.label) + '</td>';
                cells += '<td class="wprss-hub-global-col" data-key="' + sk.key + '"><span class="wprss-hub-cell-value">—</span></td>';
                activeSites.forEach(function (s) {
                    cells += '<td class="wprss-hub-setting-cell" data-key="' + sk.key + '" data-site="' + s.id + '"><span class="wprss-hub-skeleton"></span></td>';
                });
                return '<tr>' + cells + '</tr>';
            }).join('');

            // Fetch settings for each site.
            activeSites.forEach(function (s) {
                api('settings/' + s.id).then(function (data) {
                    settingKeys.forEach(function (sk) {
                        var cell = tbody.querySelector('td[data-key="' + sk.key + '"][data-site="' + s.id + '"]');
                        if (cell) {
                            var val = data[sk.key] !== undefined && data[sk.key] !== null ? String(data[sk.key]) : '';
                            cell.innerHTML = '<span class="wprss-hub-cell-value">' + esc(val || '—') + '</span>';
                            cell.dataset.value = val;
                        }
                    });
                }).catch(function () {
                    settingKeys.forEach(function (sk) {
                        var cell = tbody.querySelector('td[data-key="' + sk.key + '"][data-site="' + s.id + '"]');
                        if (cell) {
                            cell.innerHTML = '<span class="wprss-hub-cell-error">Error</span>';
                        }
                    });
                });
            });

            // Inline editing on click.
            tbody.addEventListener('click', function (e) {
                var cell = e.target.closest('.wprss-hub-setting-cell');
                if (!cell || cell.querySelector('input')) return;

                var currentVal = cell.dataset.value || '';
                var input = document.createElement('input');
                input.type = 'text';
                input.value = currentVal;
                input.className = 'wprss-hub-inline-input';

                var saveIcon = document.createElement('button');
                saveIcon.className = 'button button-small';
                saveIcon.textContent = 'Save';

                cell.innerHTML = '';
                cell.appendChild(input);
                cell.appendChild(saveIcon);
                input.focus();

                function save() {
                    var newVal = input.value;
                    var siteId = cell.dataset.site;
                    var key = cell.dataset.key;
                    var body = {};
                    body[key] = newVal;

                    api('settings/' + siteId, { method: 'POST', body: body }).then(function () {
                        cell.innerHTML = '<span class="wprss-hub-cell-value">' + esc(newVal || '—') + '</span>';
                        cell.dataset.value = newVal;
                        toast('Setting saved!', 'pass');
                    }).catch(function (err) {
                        toast(err.message, 'fail');
                        cell.innerHTML = '<span class="wprss-hub-cell-value">' + esc(currentVal || '—') + '</span>';
                    });
                }

                saveIcon.addEventListener('click', save);
                input.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter') save();
                    if (ev.key === 'Escape') {
                        cell.innerHTML = '<span class="wprss-hub-cell-value">' + esc(currentVal || '—') + '</span>';
                    }
                });
            });

            // Global column click.
            tbody.addEventListener('click', function (e) {
                var cell = e.target.closest('.wprss-hub-global-col');
                if (!cell || cell.querySelector('input')) return;

                var key = cell.dataset.key;
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'wprss-hub-inline-input';

                var saveIcon = document.createElement('button');
                saveIcon.className = 'button button-small';
                saveIcon.textContent = 'Push All';

                cell.innerHTML = '';
                cell.appendChild(input);
                cell.appendChild(saveIcon);
                input.focus();

                function pushGlobal() {
                    api('settings/global', { method: 'POST', body: { option_key: key, option_value: input.value } }).then(function (res) {
                        toast('Global push queued! Job #' + (res.job_id || ''), 'pass');
                        cell.innerHTML = '<span class="wprss-hub-cell-value">' + esc(input.value || '—') + '</span>';
                    }).catch(function (err) {
                        toast(err.message, 'fail');
                        cell.innerHTML = '<span class="wprss-hub-cell-value">—</span>';
                    });
                }

                saveIcon.addEventListener('click', pushGlobal);
                input.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter') pushGlobal();
                    if (ev.key === 'Escape') {
                        cell.innerHTML = '<span class="wprss-hub-cell-value">—</span>';
                    }
                });
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Queue Page                                                         */
    /* ------------------------------------------------------------------ */

    function initQueuePage() {
        var table = document.getElementById('wprss-hub-jobs-table');
        if (!table) return;

        var tbody = table.querySelector('tbody');
        var polling = false;

        function renderJobs(jobs) {
            if (!jobs.length) {
                tbody.innerHTML = '<tr><td colspan="7">No jobs.</td></tr>';
                return;
            }

            var hasActive = false;
            tbody.innerHTML = jobs.map(function (j) {
                if (j.status === 'queued' || j.status === 'running') hasActive = true;
                var siteIds = j.site_ids || [];
                var siteNames = siteIds.map(function (sid) { return esc(siteName(sid)); }).join(', ');
                var retryBtn = j.status === 'failed' ? '<button class="button button-small wprss-hub-retry-job">Retry Failed</button>' : '';
                return '<tr data-id="' + j.id + '" class="wprss-hub-job-row">' +
                    '<td>' + j.id + '</td>' +
                    '<td>' + esc(j.job_type) + '</td>' +
                    '<td>' + siteNames + '</td>' +
                    '<td>' + badge(j.status) + '</td>' +
                    '<td>' + esc(j.created_at) + '</td>' +
                    '<td>' + esc(j.completed_at || '—') + '</td>' +
                    '<td><button class="button button-small wprss-hub-expand-job">Details</button> ' + retryBtn + '</td>' +
                '</tr>';
            }).join('');

            // Poll if active jobs.
            if (hasActive && !polling) {
                polling = true;
                setTimeout(function () {
                    polling = false;
                    loadAndRender();
                }, 10000);
            }
        }

        function loadAndRender() {
            loadSites().then(function () {
                return api('jobs');
            }).then(renderJobs).catch(function () { toast('Failed to load jobs.', 'fail'); });
        }

        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var row = btn.closest('tr');
            var id = row.dataset.id;

            if (btn.classList.contains('wprss-hub-expand-job')) {
                // Toggle detail row.
                var existing = tbody.querySelector('.wprss-hub-job-detail[data-job-id="' + id + '"]');
                if (existing) {
                    existing.remove();
                    return;
                }

                api('jobs').then(function (jobs) {
                    var job = jobs.find(function (j) { return String(j.id) === id; });
                    if (!job || !job.results) return;

                    var rowsHtml = '';
                    for (var sid in job.results) {
                        var r = job.results[sid];
                        rowsHtml += '<tr><td>' + esc(siteName(sid)) + '</td><td>' + badge(r.status) + '</td><td>' + esc(r.message) + '</td></tr>';
                    }

                    var detailRow = document.createElement('tr');
                    detailRow.className = 'wprss-hub-job-detail';
                    detailRow.dataset.jobId = id;
                    detailRow.innerHTML = '<td colspan="7"><table class="widefat"><thead><tr><th>Site</th><th>Status</th><th>Message</th></tr></thead><tbody>' + rowsHtml + '</tbody></table></td>';
                    row.after(detailRow);
                });
            }

            if (btn.classList.contains('wprss-hub-retry-job')) {
                api('jobs/' + id + '/retry', { method: 'POST' }).then(function () {
                    toast('Retry queued!', 'pass');
                    loadAndRender();
                }).catch(function (e) { toast(e.message, 'fail'); });
            }
        });

        loadAndRender();
    }

    /* ------------------------------------------------------------------ */
    /*  Logs Page                                                          */
    /* ------------------------------------------------------------------ */

    function initLogsPage() {
        var table = document.getElementById('wprss-hub-logs-table');
        if (!table) return;

        var tbody = table.querySelector('tbody');
        var pagination = document.getElementById('wprss-hub-log-pagination');
        var currentPage = 1;

        // Populate site filter dropdown.
        loadSites().then(function (sites) {
            var select = document.getElementById('wprss-hub-log-site-filter');
            sites.forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                select.appendChild(opt);
            });
        });

        function loadLogs(page) {
            currentPage = page || 1;
            var siteFilter = document.getElementById('wprss-hub-log-site-filter').value;
            var statusFilter = document.getElementById('wprss-hub-log-status-filter').value;
            var params = 'page=' + currentPage + '&per_page=50';
            if (siteFilter) params += '&site_id=' + siteFilter;
            if (statusFilter) params += '&status=' + statusFilter;

            api('logs?' + params).then(function (data) {
                if (!data.items || !data.items.length) {
                    tbody.innerHTML = '<tr><td colspan="5">No logs found.</td></tr>';
                    pagination.innerHTML = '';
                    return;
                }

                tbody.innerHTML = data.items.map(function (log) {
                    return '<tr>' +
                        '<td>' + esc(log.created_at) + '</td>' +
                        '<td>' + esc(siteName(log.site_id)) + '</td>' +
                        '<td>' + esc(log.action) + '</td>' +
                        '<td>' + badge(log.status) + '</td>' +
                        '<td>' + esc(log.message) + '</td>' +
                    '</tr>';
                }).join('');

                // Pagination.
                if (data.pages > 1) {
                    var html = '';
                    for (var p = 1; p <= data.pages; p++) {
                        if (p === currentPage) {
                            html += '<span class="tablenav-pages-navspan button disabled">' + p + '</span> ';
                        } else {
                            html += '<a href="#" class="button wprss-hub-log-page" data-page="' + p + '">' + p + '</a> ';
                        }
                    }
                    pagination.innerHTML = html;
                } else {
                    pagination.innerHTML = '';
                }
            }).catch(function () { toast('Failed to load logs.', 'fail'); });
        }

        document.getElementById('wprss-hub-log-filter-btn').addEventListener('click', function () { loadLogs(1); });

        pagination.addEventListener('click', function (e) {
            var link = e.target.closest('.wprss-hub-log-page');
            if (!link) return;
            e.preventDefault();
            loadLogs(parseInt(link.dataset.page));
        });

        document.getElementById('wprss-hub-log-prune-btn').addEventListener('click', function () {
            if (!confirm('Delete all logs older than 30 days?')) return;
            api('logs/prune', { method: 'DELETE' }).then(function (data) {
                toast('Deleted ' + (data.deleted || 0) + ' log entries.', 'pass');
                loadLogs(1);
            }).catch(function (e) { toast(e.message, 'fail'); });
        });

        loadLogs(1);
    }

    /* ------------------------------------------------------------------ */
    /*  Health Bar                                                         */
    /* ------------------------------------------------------------------ */

    function initHealthBar() {
        // Only render on wprss-hub pages.
        if (!document.querySelector('.wprss-hub-wrap')) return;

        var bar = document.createElement('div');
        bar.id = 'wprss-hub-health-bar';
        bar.className = 'wprss-hub-health-bar';
        bar.innerHTML = '<div class="wprss-hub-health-bar-inner"><span class="wprss-hub-skeleton" style="width:200px;height:16px;display:inline-block;"></span></div>';
        document.body.appendChild(bar);

        api('health').then(function (sites) {
            var inner = bar.querySelector('.wprss-hub-health-bar-inner');
            if (!sites.length) {
                inner.textContent = 'No sites configured.';
                return;
            }
            inner.innerHTML = sites.map(function (s) {
                var dotClass = 'wprss-hub-health-dot--' + s.status;
                var lastFetch = s.last_fetch ? timeAgo(s.last_fetch) : 'never';
                return '<span class="wprss-hub-health-chip">' +
                    '<span class="wprss-hub-health-dot ' + dotClass + '"></span>' +
                    esc(s.site_name) +
                    ' <small>(' + lastFetch + ' | ' + s.item_count + ' items' +
                    (s.error_count > 0 ? ' | ' + s.error_count + ' errors' : '') +
                    ')</small></span>';
            }).join('');
        }).catch(function () {
            bar.querySelector('.wprss-hub-health-bar-inner').textContent = 'Health check failed.';
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Init on DOMContentLoaded                                           */
    /* ------------------------------------------------------------------ */

    document.addEventListener('DOMContentLoaded', function () {
        initSitesPage();
        initFeedsPage();
        initSettingsPage();
        initQueuePage();
        initLogsPage();
        initHealthBar();
    });

})();
