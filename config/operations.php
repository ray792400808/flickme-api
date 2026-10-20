<?php

return ['media_hosts' => array_filter(explode(',', env('OPERATIONS_MEDIA_HOSTS', '')))];
