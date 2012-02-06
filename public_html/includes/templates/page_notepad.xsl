<?xml version='1.0'?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl" exclude-result-prefixes="php">
    <xsl:include href="master.xsl"/>
    <xsl:output method="html" use-character-maps="chars" doctype-public="-//W3C//DTD HTML 4.0//EN"/>

    <xsl:template name="body">
        <div id="dialogs">
            <xsl:if test="/template/editor/user/@manager = 1">
                <div class="manage">
                    <form autocomplete="off" method="POST" name="toggle">
                        <div class="users">
                            <div class="header">
                                <div class="username">Nickname/Username</div>
                                <div class="is">Is manager?</div>
                                <div class="is">Is enabled?</div>
                                <div class="clear"></div>
                            </div>
                            <div class="body">
                            </div>
                        </div>
                    </form>
                    <div class="template">
                        <div class="row">
                            <div class="user">
                                Nickname: <span class="nickname"></span><br/>
                                <span class="username"></span><br/>
                            </div>
                            <div class="is">
                                <input type="checkbox" name="is_manager"/>
                            </div>
                            <div class="is">
                                <input type="checkbox" name="is_enabled"/>
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>
                </div>
            </xsl:if>
            <div class="create">
                <br/>
                <div class="label">Name:</div>
                <div class="value">
                    <input type="text" name="name" value=""/>
                </div>
                <div class="clear"></div>

                <div class="private">
                    <label for="create_private">Check to make private:</label>
                    <input type="checkbox" name="private" id="create_private" checked="checked"/>
                </div>
            </div>
            <div class="rename">
                <br/>
                <div class="label">New name:</div>
                <div class="value">
                    <input type="text" name="name" value=""/>
                </div>
                <div class="clear"></div>

                <div class="private">
                    <label for="rename_private">Check to make private:</label>
                    <input type="checkbox" name="private" id="rename_private" checked="checked"/>
                </div>

                <!-- a place to store the id of what we are renaming -->
                <input type="hidden" name="id" value=""/>
            </div>
            <div class="profile">
                <div class="openid">
                    Logged in as:<br/>
                    <xsl:value-of select="/template/editor/user/openid"/>
                </div>

                <div class="label">Nickname:</div>
                <div class="value">
                    <input type="text" name="username" value=""/>
                </div>
                <div class="clear"></div>
            </div>
        </div>
        <div id="message">
            Please choose from the list of notepads above or create a new pad.
        </div>
        <div id="entries">
            <div class="list">
                <form autocomplete="off">
                    <input type="button" name="new" value="New"/>
                    <xsl:text> </xsl:text>
                    <select size="1" name="name">
                        <option value="">Select a notepad...</option>
                        <optgroup label="Public Notepads" class="public">
                            <xsl:if test="count(/template/notepads/public/notepad) = 0">
                                <option value="">No public notepads found.</option>
                            </xsl:if>
                            <xsl:for-each select="/template/notepads/public/notepad">
                                <option value="{@id}"><xsl:value-of select="."/></option>
                            </xsl:for-each>
                        </optgroup>
                        <optgroup label="Private Notepads" class="private">
                            <xsl:if test="count(/template/notepads/private/notepad) = 0">
                                <option value="">No private notepads found.</option>
                            </xsl:if>
                            <xsl:for-each select="/template/notepads/private/notepad">
                                <option value="{@id}"><xsl:value-of select="."/></option>
                            </xsl:for-each>
                        </optgroup>
                    </select>
                    <input type="submit" value="Open"/>
                    <input type="button" name="rename" value="Rename"/>
                    <input type="button" name="delete" value="Delete"/>
                </form>
            </div>
            <div class="working"><img src="support/spinning.gif" alt=""/></div>
            <div class="menu">
                <a id="username" href="javascript:void(0);" onclick="notepad.events.profile.open();"><xsl:value-of select="/template/editor/user/nickname"/></a> | 
                <xsl:if test="/template/editor/user/@manager = 1">
                    <a href="javascript:void(0);" onclick="notepad.events.manage.open();">manage</a> | 
                </xsl:if>
                <a href="logout.php">logout</a>
            </div>
            <div class="clear"></div>
        </div>
        <div id="body"></div>
        <div style="clear: both;"></div>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                notepad.initialize('<xsl:value-of select="/template/editor/url"/>',
                                   '<xsl:value-of select="/template/editor/user/nickname"/>');

                // set up the default content
                jQuery('#frame').html(notepad.clear());

                // open a notepad on submission on the form
                jQuery('#entries div.list form').submit(function (event) {
                    event.preventDefault();
                    var form = jQuery(this).closest('form');
                    var id = jQuery(form).find('select[name="name"]').val();
                    if (id) {
                        notepad.open(id);
                    } else {
                        alert('You must select a notepad before trying to open it.');
                    }
                });

                // delete a notepad
                jQuery('#entries div.list form input[name="delete"]').click(function (event) {
                    event.preventDefault();
                    var form = jQuery(this).closest('form');
                    var id = jQuery(form).find('select[name="name"]').val();
                    var name = jQuery(form).find('select[name="name"] option[value="' + id + '"]').text();
                    if (id) {
                        if (confirm('Are you sure you want to delete the pad named "' + name + '".')) {
                            notepad.destroy(id);
                        }
                    } else {
                        alert('You must select a notepad before trying to delete it.');
                    }
                });

                // rename a notepad
                jQuery('#entries div.list form input[name="rename"]').click(function (event) {
                    event.preventDefault();
                    var form = jQuery(this).closest('form');
                    var id = jQuery(form).find('select[name="name"]').val();
                    if (id) {
                        notepad.events.rename.open(id);
                    } else {
                        alert('You must select a notepad before trying to rename it.');
                    }
                });

                // create a new notepad
                jQuery('#entries div.list form input[name="new"]').click(function (event) {
                    event.preventDefault();
                    notepad.events.create.open();
                });

                // create the dialogs
                notepad.events.create.create();
                notepad.events.rename.create();
                notepad.events.profile.create();

                <xsl:if test="/template/editor/user/@manager = 1">
                    // management dialogs
                    notepad.events.manage.create();
                </xsl:if>

                // if the window resizes, make sure that the editor is the same size as the window
                jQuery(window).resize(notepad.resize);
                jQuery(window).trigger('resize');
            });
        </script>
    </xsl:template>
</xsl:stylesheet>
