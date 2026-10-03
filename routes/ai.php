<?php

use App\Mcp\Servers\lyrpickleball;
use Laravel\Mcp\Facades\Mcp;

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);

//for HTTTP SERVER FOR ONLINE
Mcp::web('/mcp/lyrpickleball', lyrpickleball::class);

//for HTTTP SERVER FOR LOCALHOST
Mcp::local('lyrpickleball', lyrpickleball::class);
