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

class CreateViewFormCreateCommand extends Command
{

    protected $name = 'gerador:make-field';
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
    protected $required = 0;
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
        $table_relacionamentos = DB::select("SELECT a.attname AS name,a.attname AS fk, clf.relname AS tabela_ref, af.attname AS atributo_ref FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_class cl ON (a.attrelid = cl.oid AND cl.relkind = 'r') JOIN pg_catalog.pg_namespace n ON (n.oid = cl.relnamespace) JOIN pg_catalog.pg_constraint ct ON (a.attrelid = ct.conrelid AND ct.confrelid != 0 AND ct.conkey[1] = a.attnum) JOIN pg_catalog.pg_class clf ON (ct.confrelid = clf.oid AND clf.relkind = 'r') JOIN pg_catalog.pg_namespace nf ON (nf.oid = clf.relnamespace) JOIN pg_catalog.pg_attribute af ON (af.attrelid = ct.confrelid AND af.attnum = ct.confkey[1]) WHERE cl.relname = '$tabela'");

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
                if($coluna->type == 'boolean'){
                    $i++;
                    $this->total_bol = $i;
                }elseif($coluna->type == 'fk'){
                    $i++;
                    $this->total_fk = $i;
                }
                else{
                    $i++;
                    $this->total_var = $i;
                }



                //$this->total_var = 0;


                //gera os campos do formulario
                //array_push($this->array_form,$coluna);
                $this->array_form[] = [
                    'name'=>$coluna->name,
                    'type'=>$coluna->type,
                    'max_len'=>$coluna->character_octet_length,
                    'is_nullable'=>$coluna->is_nullable,
                    'fk'=>null,
                    'tabela_ref'=>null,
                    'atributo_ref'=>null
                ];

            }
        }



        //verifica o tipo de campo
        foreach ($table_relacionamentos as $coluna){
            $this->array_form_fk[] = [
                'name'=>$coluna->name,
                'type'=>'fk',
                'max_len'=>0,
                'fk'=>$coluna->fk,
                'tabela_ref'=>$coluna->tabela_ref,
                'atributo_ref'=>$coluna->atributo_ref
            ];
        }
        //$this->array_form
        $this->array_form_merge = array_replace_recursive  ($this->array_form, $this->array_form_fk);




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
            $stub_model  = $this->files->get($this->getStub('views/create.blade.stub'));
            $stub_model_edit  = $this->files->get($this->getStub('views/edit.blade.stub'));

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




            ##GERA O FORMULARIO
            ##MONTA O FORMULARIO

            $i=0;
            $required = '';
            foreach ($this->array_form_merge as $key => $form){
                if($form['is_nullable'] == 'NO'){
                    $required = 'required';
                }else{
                    $required = '';
                }
                if($form['type'] == 'fk'){
                    $i++;




                    if($i % 2 == 0){
                        #par
                        $row_open = '';
                        $row_closed= '</div>';
                    } else {
                        $row_open = '<div class="row">';
                        $row_closed= '';
                    }

                    if($this->total_var == $i){
                        if($i % 2 == 0){
                            $a=null;
                        } else {
                            $row_open = '';
                            $row_closed= '</div>';
                        }
                    }


                    $stub_var_form = array(
                        "%LOWER_NAME%",
                        "%COLUNA_NOME%",
                        "%MAX_LEN%",
                        "%MODELNAME%",
                        '%TABLENAME%',
                        '%ROW_OPEN%',
                        '%ROW_CLOSED%',
                        '%MODULO_NOME_FIELD%',
                        '%REQUIRED%'

                    );
                    $file_var_form   = array(
                        strtolower($modulo),
                        $form['name'],
                        $form['max_len'],
                        ucfirst($tabela),
                        $tabela,
                        $row_open,
                        $row_closed,
                        $form['tabela_ref'],
                        $required

                    );
                    $imput_form_fk = $this->files->get($this->getStub('views/form-fields/select-field.blade.stub'));
                    $this->contents_form_fk[] = $this->replace($imput_form_fk,$stub_var_form,$file_var_form);



                }
                elseif ($form['type'] == 'boolean'){
                    if($form['is_nullable'] == 'NO'){
                        $i++;


                        if($i % 2 == 0){
                            #par
                            $row_open = '';
                            $row_closed= '</div>';
                        } else {
                            $row_open = '<div class="row">';
                            $row_closed= '';
                        }

                        if($this->total_var == $i){
                            if($i % 2 == 0){
                                $a=null;
                            } else {
                                $row_open = '';
                                $row_closed= '</div>';
                            }
                        }


                        $stub_var_form = array(
                            "%LOWER_NAME%",
                            "%COLUNA_NOME%",
                            "%MAX_LEN%",
                            "%MODELNAME%",
                            '%TABLENAME%',
                            '%ROW_OPEN%',
                            '%ROW_CLOSED%',
                            '%MODULO_NOME_FIELD%',
                            '%REQUIRED%'

                        );
                        $file_var_form   = array(
                            strtolower($modulo),
                            $form['name'],
                            $form['max_len'],
                            ucfirst($tabela),
                            $tabela,
                            $row_open,
                            $row_closed,
                            $form['tabela_ref'],
                            $required

                        );
                        $imput_form_fk = $this->files->get($this->getStub('views/form-fields/checkbox-field.blade.stub'));
                        $this->contents_form_fk[] = $this->replace($imput_form_fk,$stub_var_form,$file_var_form);
                    }

                }else{
                    $i++;




                    if($i % 2 == 0){
                        #par
                        $row_open = '';
                        $row_closed= '</div>';
                    } else {
                        $row_open = '<div class="row">';
                        $row_closed= '';
                    }

                    if($this->total_var == $i){
                        if($i % 2 == 0){
                           $a=null;
                        } else {
                            $row_open = '';
                            $row_closed= '</div>';
                        }
                    }

                    $stub_var_form = array(
                        "%LOWER_NAME%",
                        "%COLUNA_NOME%",
                        "%MAX_LEN%",
                        "%MODELNAME%",
                        '%TABLENAME%',
                        '%ROW_OPEN%',
                        '%ROW_CLOSED%',
                        '%MODULO_NOME_FIELD%',
                        '%REQUIRED%'

                    );
                    $file_var_form   = array(
                        strtolower($modulo),
                        $form['name'],
                        $form['max_len'],
                        ucfirst($tabela),
                        $tabela,
                        $row_open,
                        $row_closed,
                        $form['tabela_ref'],
                        $required

                    );
                    $imput_form = $this->files->get($this->getStub('views/form-fields/input-field.blade.stub'));
                    $this->contents_form[] = $this->replace($imput_form,$stub_var_form,$file_var_form);


                }
            }
            if(empty($this->contents_form_fk)){
                $merge_form = $this->contents_form;
            }else{
                $merge_form = array_merge ($this->contents_form,$this->contents_form_fk);
            }





            $form_file = null;
            foreach ($merge_form as $form){
                $form_file .= $form;
            }

            $stub_var = array(
                "%FIELDS_FORM%",
                "%LOWER_NAME%",

            );
            $file_var   = array(
                $form_file,
                strtolower($modulo),

            );





            //$this->array_form_merge
            $stub_model_form  = $this->files->get($this->getStub('views/form.blade.stub'));
            $contents_form = $this->replace($stub_model_form,$stub_var,$file_var);


            $path = $this->getDestinationFilePath(ucfirst($modulo),'form');
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

            $stub_model_form  = $this->files->get($this->getStub('views/sidebar.blade.stub'));
            $contents_form = $this->replace($stub_model_form,$stub_var,$file_var);


            $path = $this->getDestinationFilePath(ucfirst($modulo),'layouts/sidebar');
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
