var notepad = {};
notepad.actions = {};
notepad.events = {};
notepad.openid = {};
notepad.current = {};
notepad.timer = undefined;

notepad.initialize = function (url, username) {
    notepad.url = url;
    notepad.username = username;

    // set a timer that will periodically get updates to the list of notepads
    notepad.timer = setTimeout(notepad.update, 50000);
}

notepad.resize = function () {
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

notepad.open = function (pad_id, pad_name, pad_is_private) {
    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'open',
            'id': pad_id,
            'name': pad_name,
            'private': pad_is_private
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                notepad.spin.start();
                var id = jQuery(response).find('content > notepad').attr('id');
                var name = jQuery(response).find('content > notepad').text();
                var is_private = (jQuery(response).find('content > notepad').attr('private') === 'true') ? true : false;

                // set our current entry
                notepad.current.id = id;
                notepad.current.name = pad_name;
                notepad.current.is_private = is_private;

                // load the new list of notepads into place
                notepad.update_groups(jQuery(response).find('content > notepads > public > notepad'),
                                      jQuery(response).find('content > notepads > private > notepad'));

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
                var frame = '<iframe id="frame" src="' + notepad.url + id + '?' + params.join('&') + '"></iframe>';
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

                // make the selectbox select our new notepad
                jQuery('#entries div.list select[name="name"]').val(id);
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

notepad.destroy = function (pad_id) {
    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'destroy',
            'id': pad_id
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                if (pad_id === notepad.current.id) {
                    notepad.actions.clear();
                    notepad.current.id = undefined;
                    notepad.current.name = undefined;
                    notepad.current.is_private = undefined;
                }

                // load the new list of notepads into place
                notepad.update_groups(jQuery(response).find('content > notepads > public > notepad'),
                                      jQuery(response).find('content > notepads > private > notepad'));
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

notepad.rename = function (pad_id, pad_name, pad_is_private) {
    jQuery.ajax({
        url: 'editor.php',
        type: 'GET',
        dataType: 'xml',
        data: {
            'action': 'rename',
            'id': pad_id,
            'name': pad_name,
            'private': pad_is_private
        },
        success: function (response) {
            var errors = false;
            jQuery(response).find('errors > error').each(function (index, item) {
                alert(jQuery(item).text());
                errors = true;
            });

            if (!errors) {
                notepad.current.name = pad_name;
                notepad.current.is_private = pad_is_private;

                // load the new list of notepads into place
                notepad.update_groups(jQuery(response).find('content > notepads > public > notepad'),
                                      jQuery(response).find('content > notepads > private > notepad'));
            }
        },
        error: function () {
            alert('There was an error renaming this notepad.');
        },
        beforeSend: function () {
            notepad.spin.start();
        },
        complete: function () {
            notepad.spin.stop();
        }
    });
}

notepad.clear = function () {
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

/**
 * handle creating, opening, and renaming notepads
 */

notepad.events.create = {};
notepad.events.create.box = undefined;

notepad.events.create.create = function () {
    notepad.events.create.box = jQuery('#dialogs > div.create').dialog({
        autoOpen: false,
        draggable: false,
        modal: true,
        resizable: false,
        title: 'Create Notepad',
        width: 340,
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
    var name = jQuery.trim(jQuery(notepad.events.create.box).find('input[name="name"]').val());
    if (!name) {
        alert('No notepad name entered.');
        jQuery(notepad.events.create.box).find('input[name="name"]').focus();
        return;
    }

    var is_private = false;
    if (jQuery(notepad.events.create.box).find('input[name="private"]').is(':checked')) {
        is_private = true;
    }

    jQuery(notepad.events.create.box).find('input[name="name"]').val('');
    jQuery(notepad.events.create.box).find('input[name="private"]').removeAttr('checked').prop('checked', true);

    notepad.open(null, name, is_private);
    notepad.events.create.box.dialog('close');
}

notepad.events.create.cancel = function () {
    notepad.events.create.box.dialog('close');
}

notepad.events.rename = {};
notepad.events.rename.create = function () {
    notepad.events.rename.box = jQuery('#dialogs > div.rename').dialog({
        autoOpen: false,
        draggable: false,
        modal: true,
        resizable: false,
        title: 'Rename Notepad',
        width: 340,
        buttons: {
            "Rename": notepad.events.rename.save,
            "Cancel": notepad.events.rename.cancel
        }
    });
}

notepad.events.rename.open = function (id) {
    notepad.events.rename.box.dialog('open');

    var option = jQuery('#entries div.list select[name="name"] option[value="' + id + '"]');
    var name = jQuery(option).text();
    var is_private = (jQuery(option).closest('optgroup').hasClass('public')) ? false : true;

    jQuery(notepad.events.rename.box).find('input[name="name"]').val(name);
    if (is_private) {
        jQuery(notepad.events.rename.box).find('input[name="private"]').removeAttr('checked').prop('checked', true);
    } else {
        jQuery(notepad.events.rename.box).find('input[name="private"]').removeAttr('checked');
    }
    jQuery(notepad.events.rename.box).find('input[name="id"]').val(id);
}

notepad.events.rename.save = function () {
    var name = jQuery.trim(jQuery(notepad.events.rename.box).find('input[name="name"]').val());
    if (!name) {
        alert('No notepad name entered.');
        jQuery(notepad.events.rename.box).find('input[name="name"]').focus();
        return;
    }

    var is_private = false;
    if (jQuery(notepad.events.rename.box).find('input[name="private"]').is(':checked')) {
        is_private = true;
    }

    var id = jQuery(notepad.events.rename.box).find('input[name="id"]').val();

    jQuery(notepad.events.rename.box).find('input[name="name"]').val('');
    jQuery(notepad.events.rename.box).find('input[name="private"]').removeAttr('checked').prop('checked', true);

    notepad.rename(id, name, is_private);
    notepad.events.rename.box.dialog('close');
}

notepad.events.rename.cancel = function () {
    notepad.events.rename.box.dialog('close');
}

/**
 * manage box interface
 */

notepad.events.manage = {};
notepad.events.manage.box = undefined;

notepad.events.manage.create = function () {
    notepad.events.manage.box = jQuery('#dialogs > div.manage').dialog({
        autoOpen: false,
        draggable: false,
        modal: true,
        resizable: false,
        title: 'Manage Users',
        width: 550,
        height: 250,
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
                    jQuery(clone).find('span.username').html(jQuery(item).find('username').text());
                    jQuery(clone).find('span.nickname').html(jQuery(item).find('nickname').text());
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

/**
 * profile box interface
 */

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
                if (notepad.current.id) notepad.open(notepad.current.id, notepad.current.name, notepad.current.is_private);

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

/**
 * code for logging in is below
 */

notepad.openid.select = function (e, source) {
    var provider = notepad.openid.providers[source];
    if (!provider) return;

    // enable the submit button and highlight what has been selected
    var form = jQuery(e).closest('form');
    jQuery(form).find('input[type="submit"]').removeAttr('disabled');
    jQuery(form).find('div.boxes').find('div').removeClass('highlight');
    jQuery(e).closest('div').removeClass('nohighlight').addClass('highlight');

    // always want to clear the input area box so that nothing comes across accidentally
    var input_area = jQuery(form).find('div.input');
    jQuery(input_area).empty();

    // if we need to provide the user a box to enter stuff, do it here
    if (provider["label"]) {
        var value = '';
        if (provider["id"] == 'openid') { value = 'http://'; }
        jQuery(input_area).append(provider["label"] + '<br/>');
        jQuery(input_area).append('<input type="text" name="username" value="" autocorrect="off" autocapitalize="off"/>');
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

    // disable the submit buttons
    jQuery(form).find('input[type="submit"]').attr('disabled', 'disabled');
}

/**
 *  code for displaying the busy signal is below
 */

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

/**
 * replace the list of groups with an updated list
 */

notepad.update = function () {
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
                // load the new list of notepads into place
                notepad.update_groups(jQuery(response).find('content > notepads > public > notepad'),
                                      jQuery(response).find('content > notepads > private > notepad'));
            }
        },
        beforeSend: function () {
            notepad.spin.start();
        },
        complete: function () {
            notepad.spin.stop();
            notepad.timer = setTimeout(notepad.update, 50000);
        }
    });
}

notepad.update_groups = function (public_notepads, private_notepads) {
    // get the select box
    var selectbox = jQuery('#entries div.list select[name="name"]');

    // see what is selected
    var selected = jQuery(selectbox).val();

    var public_notepad_count = 0;
    var public_notepad_optgroup = jQuery(selectbox).find('optgroup.public');
    jQuery(public_notepad_optgroup).empty();
    jQuery(public_notepads).each(function (index, item) {
        var key = jQuery(item).attr('id');
        var value = jQuery(item).text();
        jQuery(public_notepad_optgroup).append('<option value="' + key + '">' + value + '</option>');
        public_notepad_count++;
    });
    if (!public_notepad_count) {
        jQuery(public_notepad_optgroup).append('<option value="">No public notepads found.</option>');
    }

    var private_notepad_count = 0;
    var private_notepad_optgroup = jQuery(selectbox).find('optgroup.private');
    jQuery(private_notepad_optgroup).empty();
    jQuery(private_notepads).each(function (index, item) {
        var key = jQuery(item).attr('id');
        var value = jQuery(item).text();
        jQuery(private_notepad_optgroup).append('<option value="' + key + '">' + value + '</option>');
        private_notepad_count++;
    });
    if (!private_notepad_count) {
        jQuery(private_notepad_optgroup).append('<option value="">No private notepads found.</option>');
    }

    // re-select the pad on the group
    jQuery(selectbox).val(selected);
}

