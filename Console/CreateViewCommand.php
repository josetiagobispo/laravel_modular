<?php

namespace Modules\Gerador\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Modules\Gerador\Entities\DataBase;
use Modules\Help\Entities\Cli;
use Nwidart\Modules\Exceptions\FileAlreadyExistException;
use Nwidart\Modules\Generators\FileGenerator;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

//

class CreateViewCommand extends Command
{

    protected $name = 'gerador:make-view';
    protected $description = 'Gere uma view';
    protected $command;
    protected $files;
    protected $pk_field;
    protected $fields_array = []; //['value1','value2','value3']


    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     */

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $modulo = $this->argument('NomeModulo');
        $tabela = $this->option('tabela');
        if(empty($tabela)){
            echo 'Utilize a opção --tabela=? Exemplo:php artisan make-controller example --tabela=cli';
            die();
        }

        //$table_db =  new DataBase();
        //select * from pg_tables where schemaname='public';

        $table_db = DB::select("SELECT column_name as name , data_type as type, ordinal_position, is_nullable, character_octet_length  FROM information_schema.COLUMNS WHERE TABLE_NAME = '$tabela'");
        $pk_fk_table = DB::select("SELECT column_name as name  FROM information_schema.KEY_COLUMN_USAGE where TABLE_NAME = '$tabela' LIMIT 1");
        $pk = $pk_fk_table[0]->name;

        foreach ($table_db as $coluna){
            if($pk_fk_table[0]->name == $coluna->name){
                unset($coluna->{$pk_fk_table[0]->name});
                $this->pk_field = $coluna->name;
            }else{
                //$array_add[$coluna->name] =  $coluna->name;
                array_push($this->fields_array,$coluna->name);
            }
        }

        try {
            $fillable = '<th>#</th>';
            $fillableTable = '';

            $keyword = null;
            $perPage = 0;
            $this->fields_array = array_diff( $this->fields_array, config('gerador.ignore_fields'));
            foreach ($this->fields_array as $key => $field){
                if($key > 0 and $key < 6){
                    $fillable .= "<th>$field</th>";
                    $fillableTable .= '<td>{{ $item->'.$field.' }}</td>';
                }

            }
            $fillable .= "<th>@lang('app.acoes')</th>";
           // $fillable = substr($fillable, 0, -1);
            $stub_model  = $this->files->get($this->getStub('views/index.blade.stub'));
            $stub_var = array(
                "%MODELNAME%",
                '%TABLENAME%',
                "%TABLEPK%",
                "%NAMESPACE%",
                "%FILLABLES%",
                "%FILLABLES_TAbles%",
                "%LOWER_NAME%",
                "%MODULO%",
                "%keyword%",
                "%perPage%"
            );
            $file_var   = array(
                ucfirst($tabela),
                $tabela,
                $this->pk_field,
                config('gerador.namespace')."\\".ucfirst($modulo)."\\Http\\Controllers",
                $fillable,
                $fillableTable,
                strtolower($modulo),
                ucfirst($modulo),
                '%$keyword%',
                '$perPage'
            );


            $contents = $this->replace($stub_model,$stub_var,$file_var);

            $path = $this->getDestinationFilePath(ucfirst($modulo),'index');
            if (!$this->laravel['files']->isDirectory($dir = dirname($path))) {
                $this->laravel['files']->makeDirectory($dir, 0777, true);
            }

            try {
                $overwriteFile = config('gerador.force_create_file');
                (new FileGenerator($path, $contents))->withFileOverwrite($overwriteFile)->generate();

                $this->info("Created CRUD LARAVEL ***: {$path}");
            } catch (FileAlreadyExistException $e) {
                $this->error("File : {$path} already exists.");
            }
        } catch (FileNotFoundException $e) {
            print_r($e);

        }
    }

    protected function getDestinationFilePath($module,$file,$read='views')
    {
        $path = $this->laravel['modules']->getModulePath($module);

        $modelPath = GenerateConfigReader::read($read);

        return $path . $modelPath->getPath() . '/' . $file . '.blade.php';
    }

    protected function replace($stub, $stub_var, $file_var)
    {
        return str_replace($stub_var, $file_var, $stub);
    }

    protected function arraykey($value=false){
        if($value == false){
            return $value;
        }else{
            return  [$value] =  $value;

        }

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
