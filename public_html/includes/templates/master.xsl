<?xml version='1.0'?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl" exclude-result-prefixes="php">
    <xsl:output method="html" use-character-maps="chars" doctype-public="-//W3C//DTD HTML 4.0//EN"/>

    <!-- this is the header/footer for every page on the site -->
    <xsl:template match="/">
        <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
                <meta http-equiv="Content-Language" content="en"/>
                <meta name="ROBOTS" content="NOINDEX, NOFOLLOW"/>
                <meta name="viewport" content="width=device-width,height=device-height,maximum-scale=1,user-scalable=no"/>
                <link REL="stylesheet" TYPE="text/css" HREF="support/resources/jqueryui/jquery-ui-current.css"/>
                <link REL="stylesheet" TYPE="text/css" HREF="support/standard.css"/>
                <script type="text/javascript" src="support/resources/jquery/jquery-current.js"></script>
                <script type="text/javascript" src="support/resources/jquery/plugins/jquery.cookie.js"></script>
                <script type="text/javascript" src="support/resources/jqueryui/jquery-ui-current.js"></script>
                <script type="text/javascript" src="support/standard.js"></script>
                <title><xsl:value-of select="$title"/></title>
            </head>
            <body>
                <div id="content">
                    <xsl:call-template name="body"/>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
