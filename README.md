# EXT:staticfilecache

Static File Cache for TYPO3. Very flexible and very, very, very fast ;)

You have to install the extension in composer mode, because there are dependencies to guzzle.

Note: EXT:staticfilecache is a fork of EXT:nc_staticfilecache (EXT:fl_staticfilecach before) and has a lots of improvements.

# Behind Proxy

If your proxy processes the ssl encryption you might have to set the information for the backend:

    proxy_set_header X-Forwarded-Proto "https";
 
