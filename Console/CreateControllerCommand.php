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

class CreateControllerCommand extends Command
{

    protected $name = 'gerador:make-controller';
    protected $description = 'Gere um controllador';
    protected $command;
    protected $files;
    protected $pk_field;
    protected $tem_relacionamento;
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
        $table_relacionamentos = DB::select("SELECT a.attname AS name,a.attname AS fk, clf.relname AS tabela_ref, af.attname AS atributo_ref FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_class cl ON (a.attrelid = cl.oid AND cl.relkind = 'r') JOIN pg_catalog.pg_namespace n ON (n.oid = cl.relnamespace) JOIN pg_catalog.pg_constraint ct ON (a.attrelid = ct.conrelid AND ct.confrelid != 0 AND ct.conkey[1] = a.attnum) JOIN pg_catalog.pg_class clf ON (ct.confrelid = clf.oid AND clf.relkind = 'r') JOIN pg_catalog.pg_namespace nf ON (nf.oid = clf.relnamespace) JOIN pg_catalog.pg_attribute af ON (af.attrelid = ct.confrelid AND af.attnum = ct.confkey[1]) WHERE cl.relname = '$tabela'");
        if(empty($table_relacionamentos)){
            $this->tem_relacionamento = false;
        }else{
            $this->tem_relacionamento = true;
        }

        foreach ($table_db as $coluna){
            if($pk_fk_table[0]->name == $coluna->name){
                unset($coluna->{$pk_fk_table[0]->name});
                $this->pk_field = $coluna->name;
            }else{
                //$array_add[$coluna->name] =  $coluna->name;
                array_push($this->fields_array,$coluna->name);
            }
        }
        ##RELACIONAMENTOS
        $_modulo = strtolower($modulo);
        if($this->tem_relacionamento == true){
        $relacionamentos=null;
        $view_compact=null;
        $name_space=null;
        foreach ($table_relacionamentos as $rel){
            $db_coluna_nome = $this->getDB($rel->tabela_ref);
            $nome_do_modulo = $this->ask("Qual o nome do Modulo da tabela $rel->tabela_ref?");

            $relacionamentos[] = "\n        ".'$'.$rel->tabela_ref." = ".ucfirst($rel->tabela_ref)."::pluck('$db_coluna_nome','$rel->atributo_ref')->toArray();\n";
            $view_compact[] = "'$rel->tabela_ref',";
            $modulo_namespace = ucfirst($rel->tabela_ref);
            $name_space[] = "use Modules\\$nome_do_modulo\Entities\\$modulo_namespace;\n";
            $this->info("*** Tabelas Relacionadas ***: $rel->tabela_ref");
        }
        $rel_template = null;
        foreach ($relacionamentos as $template){
            $rel_template .= $template;
        }
        $compact_template = null;
        foreach ($view_compact as $template){
            $compact_template .= $template;
        }

        $name_space_template = null;
        foreach ($name_space as $template){
            $name_space_template .= $template;
        }



        $_compact = substr($compact_template,0,-1);
        $compact_template = "return view('$_modulo::create',compact($_compact));";
        }else{
            $name_space_template = '';
            $rel_template = '';
            $compact_template = "return view('$_modulo::create');";
            $_compact = '';
        }



        try {
            $fillable = null;
            $keyword = null;
            $perPage = 0;
            $this->fields_array = array_diff( $this->fields_array, config('gerador.ignore_fields'));
            foreach ($this->fields_array as $key => $field){
                if($key <= 0){
                    $fillable .= ucfirst($tabela)."::where('$field', 'LIKE', '%keyword%')";
                }elseif ($key > 0 and $key < 6){
                    $fillable .= "->orWhere('$field', 'LIKE', '%keyword%')";
                }

            }
            $fillable .= "->latest()->paginate(%perPage%);";
           // $fillable = substr($fillable, 0, -1);
            $stub_model  = $this->files->get($this->getStub('controller.stub'));
            $stub_var = array(
                "%MODELNAME%",
                '%TABLENAME_PRIMEIRA_MAISCULA%',
                '%TABLENAME%',
                "%TABLEPK%",
                "%NAMESPACE%",
                "%FILLABLES%",
                "%LOWER_NAME%",
                "%MODULO%",
                "%keyword%",
                "%perPage%",
                "%CLASS_SELECT%",
                "%VIEW_COMPACT%",
                "%USE_RELACIONAMENTOS%",
                "%LIST_COMPACT%"
            );
            $file_var   = array(
                ucfirst($tabela),
                ucfirst($tabela),
                $tabela,
                $this->pk_field,
                config('gerador.namespace')."\\".ucfirst($modulo)."\\Http\\Controllers",
                $fillable,
                strtolower($modulo),
                ucfirst($modulo),
                '%$keyword%',
                '$perPage',
                $rel_template,
                $compact_template,
                $name_space_template,
                $_compact




            );


            $contents = $this->replace($stub_model,$stub_var,$file_var);

            $path = $this->getDestinationFilePath(ucfirst($modulo),ucfirst($modulo));
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

    protected function getDestinationFilePath($module,$file,$read='controller')
    {
        $path = $this->laravel['modules']->getModulePath($module);

        $modelPath = GenerateConfigReader::read($read);

        return $path . $modelPath->getPath() . '/' . $file . 'Controller.php';
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

    protected function getDB($tabela,$select=true){
        $table_db = DB::select("SELECT column_name as name , data_type as type, ordinal_position, is_nullable, character_octet_length  FROM information_schema.COLUMNS WHERE TABLE_NAME = '$tabela'");
        if($select == true){
            $i=0;
            foreach ($table_db as $tb){
                if($tb->type == 'character varying'){
                    $i++;
                    if($i == 1){
                        return $tb->name;
                    }
                }
            }
        }else{
            return $table_db;
        }


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
