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

class CreateLangCreateCommand extends Command
{

    protected $name = 'gerador:make-lang';
    protected $description = 'Gere uma filds';
    protected $command;
    protected $files;
    protected $pk_field;
    protected $fields_array = []; //['value1','value2','value3']
    protected $array_form_merge = [] ;
    protected $array_form = [] ;
    protected $array_form_fk = [] ;
    protected $contents_form = [] ;
    protected $total_fk = 0 ;
    protected $total_bol = 0 ;
    protected $total_var = 0;
    protected $total_int = 0;
    protected $total_float = 0;
    /**
     * @var array
     */
    protected $contents_form_fk;


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
            echo 'Utilize a opção --tabela=? Exemplo:php artisan gerador:make-view-form example --tabela=cli';
            die();
        }

        //$table_db =  new DataBase();
        //select * from pg_tables where schemaname='public';

        $table_db = DB::select("SELECT column_name as name , data_type as type, ordinal_position, is_nullable, character_octet_length  FROM information_schema.COLUMNS WHERE TABLE_NAME = '$tabela'");
        $pk_fk_table = DB::select("SELECT column_name as name  FROM information_schema.KEY_COLUMN_USAGE where TABLE_NAME = '$tabela' LIMIT 1");
        $pk = $pk_fk_table[0]->name;

        $i=0;
        foreach ($table_db as $coluna){
            if($pk_fk_table[0]->name == $coluna->name){
                unset($coluna->{$pk_fk_table[0]->name});
                $this->pk_field = $coluna->name;
            }else{
                //$array_add[$coluna->name] =  $coluna->name;
                array_push($this->fields_array,$coluna->name);
                $this->contents_form[] = "'$coluna->name' => '$coluna->name',";




            }
        }
        try {
            $conteudo=null;

            $stub_lang  = $this->files->get($this->getStub('views/lang.stub'));
            foreach ($this->contents_form as $form){
                $conteudo .= '        '.$form."\n";
            }
            ;


            $stub_var = array(
                "%TRADUCAO%",

            );
            $file_var   = array(
                $conteudo
            );

            $contents_form = $this->replace($stub_lang,$stub_var,$file_var);


            $path = $this->getDestinationFilePath(ucfirst($modulo),'app');
            if (!$this->laravel['files']->isDirectory($dir = dirname($path))) {
                $this->laravel['files']->makeDirectory($dir, 0777, true);
            }

            try {
                $overwriteFile = config('gerador.force_create_file');
                (new FileGenerator($path, $contents_form))->withFileOverwrite($overwriteFile)->generate();

                $this->info("Created CRUD LARAVEL ***: {$path}");
            } catch (FileAlreadyExistException $e) {
                $this->error("File : {$path} already exists.");
            }

        } catch (FileNotFoundException $e) {
            print_r($e);

        }
    }

    protected function getDestinationFilePath($module,$file,$read='lang')
    {
        $path = $this->laravel['modules']->getModulePath($module);

        $modelPath = GenerateConfigReader::read($read);

        return $path . $modelPath->getPath() . '/' . $file . '.php';
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
