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
                                <div class="username">Username</div>
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
                                <div class="username"></div>
                                <div class="clear"></div>
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
                <div class="label">Pad Name:</div>
                <div class="value">
                    <input type="text" name="pad" value=""/>
                </div>
                <div class="clear"></div>

                <div class="private">
                    <label for="create_private">Check to make private:</label>
                    <input type="checkbox" name="private" id="create_private" checked="checked"/>
                </div>
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
                    <select size="1" name="name">
                        <option value="">Select a notepad...</option>
                        <optgroup label="Public Notepads" class="public">
                            <xsl:if test="count(/template/entries/public/entry) = 0">
                                <option value="">No public notepads found.</option>
                            </xsl:if>
                            <xsl:for-each select="/template/entries/public/entry">
                                <option value="{@key}"><xsl:value-of select="."/></option>
                            </xsl:for-each>
                        </optgroup>
                        <optgroup label="Private Notepads" class="private">
                            <xsl:if test="count(/template/entries/private/entry) = 0">
                                <option value="">No private notepads found.</option>
                            </xsl:if>
                            <xsl:for-each select="/template/entries/private/entry">
                                <option value="{@key}"><xsl:value-of select="."/></option>
                            </xsl:for-each>
                        </optgroup>
                    </select>
                    <li class="buttonli"><a title="Open this pad">
                        <div class="buttonicon">
                            <input type="submit" class="AdminIcon" value="" style="background-position:0px 18px;"/>
                        </div></a>
                    </li>
                    <li class="buttonli" id="DeleteIcon"><a title="Delete this pad">
                        <div class="buttonicon">
                            <input type="button" name="delete" class="AdminIcon" value="" style="background-position:0px 1px;"/>
                        </div></a>
                    </li>
                </form>
            </div>
            <div class="working"><img src="support/spinning.gif" alt=""/></div>
            <div class="menu">
                <xsl:if test="/template/editor/user/@manager = 1">
                    <a href="javascript:void(0);" onclick="notepad.events.manage.open();">manage</a> |
                </xsl:if>

                <div class="RightButtons">
                        <li class="buttonli"><a title="Settings and profile" href="javascript:void(0);" onclick="notepad.events.profile.open();">
                            <div class="buttonicon">
                                <input type="button" name="Settings and profile" class="AdminIcon" value="" style="background-position:0px 69px"/>
                            </div></a>
                        </li>
                        <li class="buttonli"><a title="New Pad" onClick="notepad.events.create.open();">
                            <div class="buttonicon">
                                <input type="button" name="New Pad" class="AdminIcon" value="" style="background-position:0px 69px;"/>
                            </div></a>
                        </li>
                        <li class="buttonli"><a title="Logout" href="logout.php">
                            <div class="buttonicon">
                                <input type="button" name="Logout" class="AdminIcon" value="" style="background-position:0px 50px;"/>
                            </div></a>
                        </li>
                   </div>
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
                jQuery('#frame').html(notepad.events.clear());

                // tie the "delete" and "open" buttons
                jQuery('#entries div.list form').submit(function (event) {
                    event.preventDefault();
                    var form = jQuery(this).closest('form');
                    var pad = jQuery(form).find('select[name="name"]').val();
                    var group = jQuery(form).find('option:selected').closest('optgroup').attr('class');
                    notepad.events.open(pad, group);
                });
                jQuery('#entries div.list form input[name="delete"]').click(function (event) {
                    event.preventDefault();
                    var form = jQuery(this).closest('form');
                    var pad = jQuery(form).find('select[name="name"]').val();
                    var group = jQuery(form).find('option:selected').closest('optgroup').attr('class');
                    notepad.events.destroy(pad, group);
                });

                // create the dialogs
                notepad.events.create.create();
                notepad.events.profile.create();

                <xsl:if test="/template/editor/user/@manager = 1">
                    // management dialogs
                    notepad.events.manage.create();
                </xsl:if>

                // if the window resizes, make sure that the editor is the same size as the window
                jQuery(window).resize(notepad.events.resize);
                jQuery(window).trigger('resize');
            });
        </script>
    </xsl:template>
</xsl:stylesheet>
