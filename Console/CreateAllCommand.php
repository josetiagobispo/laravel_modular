<?php

namespace Modules\Gerador\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Gerador\Entities\DataBase;
use Modules\Help\Entities\Cli;
use Nwidart\Modules\Exceptions\FileAlreadyExistException;
use Nwidart\Modules\Generators\FileGenerator;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

//

class CreateAllCommand extends Command
{

    protected $name = 'gerador:gen';
    protected $description = 'Gerar um crud completo';
    protected $command;
    protected $files;
    protected $pk_field;
    protected $fields_array = []; //['value1','value2','value3']


    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //php artisan gerador:gen Andar --tabela=andar
        $modulo = $this->argument('NomeModulo');
        $modulo = ucfirst($modulo);
        $tabela = $this->option('tabela');
        if(empty($tabela)){
            echo 'Utilize a opção --tabela=? Exemplo:php artisan gerador:gen Cliente --tabela=cli';
            die();
        }

        $table_db = DB::select("SELECT column_name as name , data_type as type, ordinal_position, is_nullable, character_octet_length  FROM information_schema.COLUMNS WHERE TABLE_NAME = '$tabela'");

        $coluna_created_at = Schema::hasColumn("$tabela", 'created_at');
        $coluna_updated_at = Schema::hasColumn("$tabela", 'updated_at');

        if(empty($coluna_created_at) and empty($coluna_updated_at)){
            Schema::table("$tabela", function (Blueprint $table) {
                $table->timestamps();
            });
        }




        $modulos = [];
        array_push($modulos,$modulo);

        $this->call('module:make', [
            'name' => $modulos
        ]);

        $this->call('gerador:make-controller', [
            'NomeModulo' => $modulo, '--tabela' => $tabela
        ]);
        $this->call('gerador:make-model', [
            'NomeModulo' => $modulo, '--tabela' => $tabela
        ]);
        $this->call('gerador:make-view', [
            'NomeModulo' => $modulo, '--tabela' => $tabela
        ]);
        $this->call('gerador:make-view-form', [
            'NomeModulo' => $modulo, '--tabela' => $tabela
        ]);
        $this->call('gerador:make-field', [
            'NomeModulo' => $modulo, '--tabela' => $tabela
        ]);
        $this->call('gerador:make-lang', [
            'NomeModulo' => $modulo, '--tabela' => $tabela
        ]);





    }

    /**
     * Get the stub file for the generator.
     *
     * @param $file
     * @return string
     */
    protected function getStub($file)
    {
        return  config('gerador.dir_stubs_template').$file;

    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['NomeModulo', InputArgument::REQUIRED, 'Nome do Modulo.'],

        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['tabela', null, InputOption::VALUE_OPTIONAL, 'Tabela Banco de Dados', null],
        ];
    }
}
