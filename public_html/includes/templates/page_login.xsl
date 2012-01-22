<?xml version='1.0'?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl" exclude-result-prefixes="php">
    <xsl:include href="master.xsl"/>
    <xsl:output method="html" use-character-maps="chars" doctype-public="-//W3C//DTD HTML 4.0//EN"/>

    <xsl:template name="body">
        <form autocomplete="off" method="POST">
            <div id="title">
                <xsl:value-of select="$title"/>
            </div>
            <div id="login">
                <!-- show an errors from the login process -->
                <xsl:for-each select="/template/errors/error">
                    <div class="error"><xsl:value-of select="."/></div>
                </xsl:for-each>

                <div id="openid_form">
                    <div>
                        Choose your login provider:
                    </div>
                    <div class="buttons">
                        <div class="center">
                            <!-- displays the list of all the providers that we accept -->
                            <xsl:for-each select="/template/openid/providers/provider">
                                <div class="button nohighlight">
                                    <a title="{name}" href="javascript:void(0);" onclick="notepad.openid.select(this, '{@id}');"
                                       style="background: #ffffff url(support/openid/images/{@id}.gif) no-repeat center center;"></a>
                                </div>
                            </xsl:for-each>
                            <div style="clear: both;"></div>
                        </div>
                    </div>
                    <div style="clear: both;"></div>

                    <!-- will show an input box if the provider requires a username -->
                    <div class="openid_input_area"></div>
                </div>

                <div style="text-align: center;">
                    <input type="checkbox" id="remember" name="remember" checked="checked" value="yes"/>
                    <label for="remember">Remember me?</label>
                </div>
                <div style="clear: both;"></div>

                <div style="text-align: center;">
                    <input type="hidden" name="provider" value=""/>
                    <input type="hidden" name="action" value="verify"/>
                    <input type="submit" name="login" value="login" disabled="disabled"/>
                </div>
            </div>
        </form>
        <script type="text/javascript">
            jQuery(window).resize(function (event) {
                var margin_top = parseInt((jQuery(window).height() - jQuery('#content').outerHeight()) / 2);
                if (margin_top &lt; 4) margin_top = 4;

                var margin_left = parseInt((jQuery(window).width() - jQuery('#content').outerWidth()) / 2);
                if (margin_left &lt; 0) margin_left = 0;

                jQuery('#content').css({
                    'position': 'absolute',
                    'marginTop': margin_top,
                    'marginLeft': margin_left
                }).width('100%');
            });

            jQuery(document).ready(function() {
                notepad.openid.providers = <xsl:value-of select="$providers"/>;
                jQuery(window).trigger('resize');
                jQuery('#login').closest('form').submit(function (event) {
                    notepad.events.login(event, this);
                });
            });
        </script>
    </xsl:template>
</xsl:stylesheet>
