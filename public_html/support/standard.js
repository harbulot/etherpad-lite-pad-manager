var notepad = {};
notepad.events = {};
notepad.openid = {};
notepad.current = {};
notepad.timer = undefined;

notepad.initialize = function (url, username) {
    notepad.url = url;
    notepad.username = username;

    // set a timer that will periodically get updates to the list of notepads
    notepad.timer = setTimeout(notepad.events.update, 50000);
}

notepad.events.resize = function () {
    var pad = jQuery('#frame');

    var pad_height_padding = pad.outerHeight(true) - pad.height();
    var height = jQuery(window).height() - jQuery('#entries').outerHeight(true) - pad_height_padding;

    var pad_width_padding = pad.outerWidth(true) - pad.width();
    var width = jQuery(window).width() - pad_width_padding;

    pad.height(height);
    pad.width(width);

    var message = jQuery('#message');
    var top_padding = ((pad.outerHeight(true) - pad.height()) / 2);
    message.css({
        top: top_padding + ((pad.height() / 2) - (message.height() / 2)),
        left: (jQuery(window).width() / 2) - (message.width() / 2)
    });
}

notepad.events.update = function () {
    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'update'
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                // load the new list of entries into place
                notepad.update_groups(jQuery(response).find('content > entries > public > entry'),
                                      jQuery(response).find('content > entries > private > entry'));
            }
        },
        beforeSend: function () {
            notepad.spin.start();
        },
        complete: function () {
            notepad.spin.stop();
            notepad.timer = setTimeout(notepad.events.update, 50000);
        }
    });
}

notepad.events.open = function (pad, group) {
    if (pad === '') return;

    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'open',
            'pad': pad,
            'group': group
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                notepad.spin.start();
                var entry = jQuery(response).find('content > entry').text();

                // set our current entry
                notepad.current.pad = pad;
                notepad.current.group = group;

                // load the new list of entries into place
                notepad.update_groups(jQuery(response).find('content > entries > public > entry'),
                                      jQuery(response).find('content > entries > private > entry'));

                // build the list of parameters
                var params = new Array();
                params.push('showControls=true');
                params.push('showChat=false');
                params.push('showLineNumber=true');
                params.push('useMonospaceFont=false');
                params.push('userName=' + notepad.username);
                params.push('noColors=true');

                // when opening a new pad, replace the iframe rather than setting a location
                // do this to prevent any issues with iframe back/forward buttons
                var frame = '<iframe id="frame" src="' + notepad.url + entry + '?' + params.join('&') + '"></iframe>';
                jQuery('#frame').remove();
                jQuery('#body').html(frame);

                jQuery('#frame').css({
                    float: 'left',
                    border: '1px solid #aaaaaa',
                    padding: '0px',
                    margin: '2px',
                }).width('100%').load(function () {
                    notepad.spin.stop();
                });

                // force the window to resize
                jQuery(window).trigger('resize');

                // hide the message
                jQuery('#message').hide();
            }
        },
        error: function () {
            alert('There was an error loading the notepad. Please try again later.');
        },
        beforeSend: function () {
            notepad.spin.start();
        },
        complete: function () {
            notepad.spin.stop();
        }
    });
}

notepad.events.destroy = function (pad, group) {
    if (pad === '') return;

    if (confirm('Are you sure you want to delete the notepad named "' + pad + '"?')) {
        jQuery.ajax({
            url: 'editor.php',
            type: 'GET',
            dataType: 'xml',
            data: {
                'action': 'delete',
                'group': group,
                'pad': pad
            },
            success: function (response) {
                var errors = false;
                jQuery(response).find('errors > error').each(function (index, item) {
                    alert(jQuery(item).text());
                    errors = true;
                });

                if (!errors) {
                    if (pad === notepad.current.pad && group === notepad.current.group) {
                        notepad.events.clear();
                        notepad.current.pad = undefined;
                        notepad.current.group = undefined
                    }

                    // load the new list of entries into place
                    notepad.update_groups(jQuery(response).find('content > entries > public > entry'),
                                          jQuery(response).find('content > entries > private > entry'));
                }
            },
            error: function () {
                alert('There was an error deleting this notepad.');
            },
            beforeSend: function () {
                notepad.spin.start();
            },
            complete: function () {
                notepad.spin.stop();
            }
        });
    }
}

notepad.events.clear = function () {
    jQuery('#frame').remove();
    jQuery('#body').html('<div id="frame"></div>');

    jQuery('#frame').css({
        float: 'left',
        border: '1px solid #aaaaaa',
        padding: '0px',
        margin: '2px',
    }).width('100%');

    jQuery('#message').show();

    // make everything line up
    jQuery(window).trigger('resize');
}

notepad.events.manage = {};
notepad.events.manage.box = undefined;

notepad.events.manage.create = function () {
    notepad.events.manage.box = jQuery('#dialogs > div.manage').dialog({
        autoOpen: false,
        draggable: false,
        modal: true,
        resizable: false,
        title: 'Manage Users',
        width: '550px',
        buttons: {
            "Close": notepad.events.manage.close
        }
    });
}

notepad.events.manage.open = function () {
    notepad.events.manage.box.dialog('open');
    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'users'
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                jQuery('div.manage div.body').empty();
                jQuery(response).find('content > users > user').each(function (index, item) {
                    var is_manager = jQuery(item).attr('manager');
                    var is_enabled = jQuery(item).attr('enabled');
                    var clone = jQuery('div.manage div.template').clone().removeClass('template');
                    jQuery(clone).find('div.username').html(jQuery(item).text());
                    jQuery(clone).find('input[name="is_manager"]').prop('checked', (is_manager == 1 ? true : false));
                    jQuery(clone).find('input[name="is_enabled"]').prop('checked', (is_enabled == 1 ? true : false));
                    jQuery('div.manage div.users div.body').append(clone);
                });

                jQuery('div.manage form input[type="checkbox"]').change(function (event) {
                    notepad.events.manage.toggle(event, this);
                });
            }
        },
        beforeSend: function () {
            notepad.spin.start();
        },
        complete: function () {
            notepad.spin.stop();
        }
    });
}

notepad.events.manage.toggle = function (event, input) {
    event.preventDefault();

    var row = jQuery(input).closest('div.row');
    var username = jQuery.trim(jQuery(row).find('div.username').text());
    var field = jQuery(input).attr('name');
    var value = jQuery(input).attr('checked') ? 1 : 0;

    jQuery.ajax({
        url: window.location,
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'toggle',
            'field': field,
            'value': value,
            'username': username
        },
        error: function () {
            alert('There was a server error when trying to execute this action.');
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                var value = jQuery(response).find('content').text();
                if (value === 'success') {
                    // NOTHING
                } else {
                    alert('Unknown response from the server.');
                }
            }
        }
    });
}

notepad.events.manage.close = function () {
    notepad.events.manage.box.dialog('close');
}

notepad.events.create = {};
notepad.events.create.box = undefined;

notepad.events.create.create = function () {
    notepad.events.create.box = jQuery('#dialogs > div.create').dialog({
        autoOpen: false,
        draggable: false,
        modal: true,
        resizable: false,
        title: 'Create Notepad',
        width: '340px',
        buttons: {
            "Create": notepad.events.create.save,
            "Cancel": notepad.events.create.cancel
        }
    });
}

notepad.events.create.open = function () {
    notepad.events.create.box.dialog('open');
}

notepad.events.create.save = function () {
    var pad = jQuery.trim(jQuery('div.create input[name="pad"]').val());
    if (!pad) {
        alert('No pad name entered.');
        jQuery('div.create input[name="pad"]').focus();
        return;
    }

    var group = 'public';
    if (jQuery('div.create input[name="private"]').is(':checked')) {
        group = 'private';
    }

    jQuery('div.create input[name="pad"]').val('');
    jQuery('div.create input[name="private"]').removeAttr('checked').prop('checked', true);

    notepad.events.open(pad, group);
    notepad.events.create.box.dialog('close');
}

notepad.events.create.cancel = function () {
    notepad.events.create.box.dialog('close');
}

notepad.events.profile = {};
notepad.events.profile.box = undefined;

notepad.events.profile.create = function () {
    notepad.events.profile.box = jQuery('#dialogs > div.profile').dialog({
        autoOpen: false,
        draggable: false,
        modal: true,
        resizable: false,
        title: 'Update Profile',
        width: '340px',
        buttons: {
            "Save": notepad.events.profile.save,
            "Cancel": notepad.events.profile.cancel
        }
    });
}

notepad.events.profile.open = function () {
    var username = jQuery.trim(jQuery('#username').text());
    jQuery('div.profile input[name="username"]').val(username);
    notepad.events.profile.box.dialog('open');
}

notepad.events.profile.save = function () {
    var username = jQuery.trim(jQuery('div.profile input[name="username"]').val());
    if (!username) {
        alert('No nickname entered.');
        jQuery('div.profile input[name="username"]').focus();
        return;
    }

    // tell the server our new nickname
    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'profile',
            'username': username
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                // set our global nickname
                notepad.username = username;

                // update the display
                jQuery('#username').text(username);

                // reload the document with the new nickname
                if (notepad.current.pad && notepad.current.group) notepad.events.open(notepad.current.pad, notepad.current.group);

                notepad.events.profile.box.dialog('close');
            }
        },
        error: function () {
            alert('An error occurred when trying to change your nickname. Please try again.');
        },
        beforeSend: function () {
            notepad.spin.start();
        },
        complete: function () {
            notepad.spin.stop();
        }
    });
}

notepad.events.profile.cancel = function () {
    notepad.events.profile.box.dialog('close');
}

notepad.openid.select = function (e, source) {
    var provider = notepad.openid.providers[source];
    if (!provider) return;

    // enable the submit button and highlight what has been selected
    var form = jQuery(e).closest('form');
    jQuery(form).find('input[type="submit"]').removeAttr('disabled');
    jQuery(form).find('.buttons').find('div').removeClass('highlight');
    jQuery(e).closest('div').addClass('highlight');

    // always want to clear the input area box so that nothing comes across accidentally
    var input_area = jQuery(form).find('.openid_input_area');
    jQuery(input_area).empty();

    // if we need to provide the user a box to enter stuff, do it here
    if (provider["label"]) {
        var value = '';
        if (provider["id"] == 'openid') { value = 'http://'; }
        jQuery(input_area).append(provider["label"] + '<br/>');
        jQuery(input_area).append('<input type="text" name="username" value=""/>');
        jQuery(input_area).show();
        jQuery(input_area).find('input[type="text"]').focus().val(value);
    }

    // record the provider so we can reference it later
    jQuery(form).find('input[type="hidden"][name="provider"]').val(source);
}

notepad.events.login = function (event, form) {
    var username_obj = jQuery(form).find('input[name="username"]');
    var remember_obj = jQuery(form).find('input[name="remember"]');

    // if logging in then make sure the user has entered stuff
    if (username_obj.length) {
        var username = jQuery.trim(username_obj.val());

        if (!username) {
            alert('You must enter a username.');
            username_obj.focus();
            event.preventDefault();
            return;
        }
    }

    // disable the cancel and submit buttons
    jQuery(form).find('input[type="submit"]').attr('disabled', 'disabled');
}

notepad.spin = {};
notepad.spin.count = 0;

notepad.spin.start = function () {
    notepad.spin.count++;

    if (notepad.spin.count > 0) {
        jQuery('#entries .working > img').show();
        document.body.style.cursor = 'wait';
    } else {
        jQuery('#entries .working > img').hide();
        document.body.style.cursor = 'default';
    }
}

notepad.spin.stop = function () {
    notepad.spin.count--;
    if (notepad.spin.count < 0) notepad.spin.count = 0;

    if (notepad.spin.count > 0) {
        jQuery('#entries .working > img').show();
        document.body.style.cursor = 'wait';
    } else {
        jQuery('#entries .working > img').hide();
        document.body.style.cursor = 'default';
    }
}

notepad.update_groups = function (public_entries, private_entries) {
    var public_notepad_count = 0;
    var public_notepad_optgroup = jQuery('#entries div.list select[name="name"] optgroup.public');
    jQuery(public_notepad_optgroup).empty();
    jQuery(public_entries).each(function (index, item) {
        var key = jQuery(item).attr('key');
        var value = jQuery(item).text();
        jQuery(public_notepad_optgroup).append('<option value="' + key + '">' + value + '</option>');
        public_notepad_count++;
    });
    if (!public_notepad_count) {
        jQuery(public_notepad_optgroup).append('<option value="">No public notepads found.</option>');
    }

    var private_notepad_count = 0;
    var private_notepad_optgroup = jQuery('#entries div.list select[name="name"] optgroup.private');
    jQuery(private_notepad_optgroup).empty();
    jQuery(private_entries).each(function (index, item) {
        var key = jQuery(item).attr('key');
        var value = jQuery(item).text();
        jQuery(private_notepad_optgroup).append('<option value="' + key + '">' + value + '</option>');
        private_notepad_count++;
    });
    if (!private_notepad_count) {
        jQuery(private_notepad_optgroup).append('<option value="">No private notepads found.</option>');
    }

    if (notepad.current.group === 'public') {
        jQuery(public_notepad_optgroup).find('option:contains("' + notepad.current.pad + '")').attr('selected', 'selected');
    }
    if (notepad.current.group === 'private') {
        jQuery(private_notepad_optgroup).find('option:contains("' + notepad.current.pad + '")').attr('selected', 'selected');
    }
}

