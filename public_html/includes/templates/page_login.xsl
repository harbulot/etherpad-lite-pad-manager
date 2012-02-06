<?xml version='1.0'?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl" exclude-result-prefixes="php">
    <xsl:include href="master.xsl"/>
    <xsl:output method="html" use-character-maps="chars" doctype-public="-//W3C//DTD HTML 4.0//EN"/>

    <xsl:template name="body">
        <form autocomplete="off" method="POST" id="homepage">
            <div class="title">
                <xsl:value-of select="$title"/>
            </div>
            <div id="login">
                <!-- show an errors from the login process -->
                <div class="errors">
                    <xsl:for-each select="/template/errors/error">
                        <xsl:value-of select="."/>
                    </xsl:for-each>
                </div>

                <div class="information">
                    Choose your login provider:
                </div>
                <div class="boxes">
                    <xsl:for-each select="/template/openid/providers/provider">
                        <div class="box nohighlight">
                            <a title="{name}" href="javascript:void(0);" onclick="notepad.openid.select(this, '{@id}');"
                               style="background: #ffffff url(support/openid/images/{@id}.gif) no-repeat center center;"></a>
                        </div>
                    </xsl:for-each>
                    <div style="clear: both;"></div>

                    <!-- will show an input box if the provider requires a username -->
                    <div class="input"></div>
                </div>

                <div style="text-align: center;">
                    <input type="checkbox" id="remember" name="remember" checked="checked" value="yes"/>
                    <label for="remember">Remember me?</label>
                </div>
                <div style="clear: both;"></div>

                <div style="text-align: center;">
                    <input type="hidden" name="provider" value=""/>
                    <input type="hidden" name="action" value="verify"/>
                    <input type="hidden" name="submit" value="go"/>
                    <input type="submit" name="login" value="login" disabled="disabled"/>
                </div>
            </div>
        </form>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                notepad.openid.providers = <xsl:value-of select="$providers"/>;

                // when the user submits send it through this handler
                jQuery('#login').closest('form').submit(function (event) {
                    notepad.events.login(event, this);
                });

                // check to see if the session cookie stuck
                if (!jQuery.cookie('NOTEPAD_SESSION')) {
                    jQuery('#login div.errors').append('<br/>Cookies must be enabled.');
                }
            });
        </script>
    </xsl:template>
</xsl:stylesheet>
