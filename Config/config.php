<?php

return [
    'name' => 'Gerador',
    'namespace' => 'Modules',
    'dir_stubs_template'=> base_path('Modules/Gerador/Resources/stubs/'),
    'custom_delimiter' => '%%',
    'ignore_fields'=> ['create_at','update_at','updated_at','created_at','mun_usrid'],
    'force_create_file'=>true


];
