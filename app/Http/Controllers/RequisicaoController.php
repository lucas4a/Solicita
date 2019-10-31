<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Requisicao_documento;
use App\Requisicao;
use App\Documento;
use App\Curso;
use App\Aluno;
use App\Perfil;
use App\User;
use Carbon\Carbon;
use App\Servidor;
use App\Unidade;

class RequisicaoController extends Controller
{
  public function index()
  {
    return view('autenticacao.formulario-requisicao');
  }
  public function getRequisicoes(Request $request){
    $documento = Documento::where('id',$request->titulo_id)->first();
    $curso = Curso::where('id',$request->curso_id)->first();
    // dd($documento);
      //Verifica se o card clicado foi igual a "TODOS"
      if($request->titulo_id == 6){
          $titulo = 'Todos';
          //$id_documentos retorna um collection. É necessário transformar para array
          //pega todas as requisições com base no id do documento e no id do curso
          $id_documentos = DB::table('requisicao_documentos')
                  ->join('requisicaos', 'requisicaos.id', '=', 'requisicao_documentos.requisicao_id')
                  ->join('perfils', 'requisicaos.perfil_id', '=', 'perfils.id')
                  ->select ('requisicao_documentos.id')
                  ->where([['curso_id', $request->curso_id],['status','Em andamento']])
                  ->get();
      }
      else {
         $titulo = $documento->tipo;
         $id_documentos = DB::table('requisicao_documentos')
                ->join('requisicaos', 'requisicaos.id', '=', 'requisicao_documentos.requisicao_id')
                ->join('perfils', 'requisicaos.perfil_id', '=', 'perfils.id')
                ->select ('requisicao_documentos.id')
                ->where([['documento_id',$request->titulo_id],['curso_id', $request->curso_id],['status','Em andamento']])
                ->get();
      }
      $id = []; //array auxiliar que pega cada item do $id_documentos
      foreach ($id_documentos as $id_documento) {
        array_push($id, $id_documento->id); //passa o id de $id_documentos para o array auxiliar $id
      }
      $listaRequisicao_documentos = Requisicao_documento::whereIn('id', $id)->orderBy('aluno_id','asc')->get(); //Pega as requisições que possuem o id do curso
      $response = [];
      foreach ($listaRequisicao_documentos as $key) {
        array_push($response, ['id' => $key->id,
                               'cpf' => $key->aluno->cpf,
                               'nome' => $key->aluno->user->name,
                               'curso' => $key->requisicao->perfil->curso->nome,
                               'status_data' => $key->status_data,
                               'status' => $key->status,
                               'detalhes' => $key->detalhes,
                              ]);
      }
      usort($response, function($a, $b){ return $a['nome'] >= $b['nome']; });
      // dd($response);
      $listaRequisicao_documentos = $response;
      return view('telas_servidor.requisicoes_servidor', compact('titulo','listaRequisicao_documentos'));
  }
    public function concluirRequisicao(Request $request){
        //dd($request);
        $arrayDocumentos = $request->checkboxLinha;
        // dd($request->checkboxLinha);
        $id_documentos = Requisicao_documento::find($arrayDocumentos);//whereIn
        if(isset($id_documentos)){
        //dd($id_documentos);
          foreach ($id_documentos as $id_documento) {
            $id_documento->status = "Concluído - Disponível para retirada";
            $id_documento->save();
          }
        }
        return redirect()->back()->with('success', 'Documento(s) Concluido(s) com Sucesso!'); //volta pra mesma url
    }
    public function storeRequisicao(Request $request){
      return redirect('confirmacao-requisicao');

    }
    public function preparaNovaRequisicao(Request $request){
          $unidades = Unidade::All();
          $usuarios = User::All();
          $alunos = Aluno::All();
          $perfis = Perfil::where('aluno_id', Auth::user()->aluno->id)->get();
          return view('autenticacao.formulario-requisicao',compact('usuarios','unidades', 'perfis', 'alunos'));
        }
    public function novaRequisicao(Request $request){
      $checkBoxDeclaracaoVinculo = $request->declaracaoVinculo;
      $checkBoxComprovanteMatricula = $request->comprovanteMatricula;
      $checkBoxHistorico = $request->historico;
      $checkBoxProgramaDisciplina = $request->programaDisciplina;
      $checkBoxOutros = $request->outros;
      // dd($request->default);
        if($checkBoxProgramaDisciplina!=''){
        $request->validate([
          'requisicaoPrograma' => ['required'],
        ]);
        }
        if($checkBoxOutros!=''){
          $request->validate([
            'requisicaoOutros' => ['required'],
          ]);
        }
        $requisicao = new Requisicao();
        $idUser = Auth::user()->id;
        $user = User::find($idUser); //Usuário Autenticado
        $aluno = Aluno::where('user_id',$idUser)->first(); //Aluno autenticado
        $perfil = Perfil::where('id',$request->default)->first();
        $arrayDocumentos = [];//Array Temporário
        date_default_timezone_set('America/Sao_Paulo');
        $date = date('d/m/Y');
        $hour =  date('H:i');
        $requisicao->data_pedido = $date;
        $requisicao->hora_pedido = $hour;
        $requisicao->perfil_id = $perfil->id;
        $requisicao->aluno_id = $aluno->id; //necessária adequação com o código de autenticação do usuário do perfil aluno
        $requisicao->save();
      if($checkBoxDeclaracaoVinculo){
        array_push($arrayDocumentos, RequisicaoController::requisitados($requisicao, 1, $perfil));
      }
      if($checkBoxComprovanteMatricula){
        array_push($arrayDocumentos, RequisicaoController::requisitados($requisicao, 2, $perfil));
      }
      if($checkBoxHistorico){
        array_push($arrayDocumentos, RequisicaoController::requisitados($requisicao, 3, $perfil));
      }
      if($checkBoxProgramaDisciplina){
        array_push($arrayDocumentos, RequisicaoController::requisitados($requisicao, 4, $perfil));
      }
      if($checkBoxOutros){
        array_push($arrayDocumentos, RequisicaoController::requisitados($requisicao, 5, $perfil));
      }
      //#Documentos
      $ano = date('Y');
      $size = count($arrayDocumentos);
      $requisicao->requisicao_documento()->saveMany($arrayDocumentos);
          $id = [];
          foreach ($arrayDocumentos as $key) {
            array_push($id, $key->documento_id);
          }
          $arrayAux = Documento::whereIn('id', $id)->get();
          // $documento = Documento::where('id',$request->titulo_id)->first();
          $curso = Curso::where('id',$request->curso_id)->first();
          return view('autenticacao.confirmacao-requisicao', compact('documentos', 'requisicao', 'arrayAux', 'size', 'ano', 'date'));
    }
    public function requisitados(Requisicao $requisicao, $id, Perfil $perfil){
      date_default_timezone_set('America/Sao_Paulo');
      $date = date('d/m/Y');
      $hour =  date('H:i');
      $documentosRequisitados = new Requisicao_documento();
      $documentosRequisitados->status_data = $date;
      $documentosRequisitados->requisicao_id = $requisicao->id;
      $documentosRequisitados->aluno_id = $perfil->aluno_id;
      $documentosRequisitados->status = 'Em andamento';
      $documentosRequisitados->documento_id = $id;
      return $documentosRequisitados;
    }
    public function confirmacaoRequisicao(Request $request){
      return redirect('/autenticacao.confirmacao-requisicao');
    }
    public function finalizaRequisicao(Request $request){
      return redirect('/home-aluno');
    }
    public function cancelaRequisicao(){
      return view('/autenticacao.home-aluno');
    }
    public function listarRequisicoesAluno(){
      $requisicao = Requisicao::paginate(10);
      return view('/home-aluno')->with($requisicao);
    }

}
