<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014-2015  Francesc Pineda Segarra  shawe.ewahs@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('articulo.php');
require_model('cliente.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('albaran_cliente.php');
require_model('familia.php');
require_model('forma_pago.php');
require_model('impuesto.php');
require_model('linea_servicio_cliente.php');
require_model('pais.php');
require_model('servicio_cliente.php');
require_model('regularizacion_iva.php');
require_model('serie.php');

class nuevo_servicio extends fs_controller
{
   public $agente;
   public $cliente;
   public $cliente_s;
   public $divisa;
   public $ejercicio;
   public $familia;
   public $forma_pago;
   public $impuesto;
   public $nuevo_servicio_url;
   public $pais;
   public $servicio;
   public $serie;

   public function __construct()
   {
      parent::__construct(__CLASS__, ucfirst(FS_SERVICIO), 'ventas', FALSE, FALSE);
   }

   protected function process()
   {
      $this->ppage = $this->page->get('ventas_servicios');
      $this->agente = FALSE;

      $servicio = new servicio_cliente();
      $this->servicio = FALSE;
      $this->cliente = new cliente();
      $this->cliente_s = FALSE;
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->familia = new familia();
      $this->forma_pago = new forma_pago();
      $this->impuesto = new impuesto();
      $this->nuevo_servicio_url = FALSE;
      $this->pais = new pais();
      $this->serie = new serie();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);

      /**
       * Comprobamos si el usuario tiene acceso a nueva_venta,
       * necesario para poder añadir líneas.
       */
      if( $this->user->have_access_to('nueva_venta', FALSE) )
      {
         $nuevopedp = $this->page->get('nueva_venta');
         if($nuevopedp)
            $this->nuevo_servicio_url = $nuevopedp->url();
      }

      if( isset($_POST['idservicio']) )
      {
         $this->servicio = $servicio->get($_POST['idservicio']);
         $this->modificar();
      }
      else if( isset($_GET['id']) )
      {
         $this->servicio = $servicio->get($_GET['id']);
      }

      if($this->servicio)
      {
         $this->page->title = $this->servicio->codigo;

         /// cargamos el agente
         if (!is_null($this->servicio->codagente))
         {
            $agente = new agente();
            $this->agente = $agente->get($this->servicio->codagente);
         }

         /// cargamos el cliente
         $this->cliente_s = $this->cliente->get($this->servicio->codcliente);

         /// comprobamos el servicio
         if ($this->servicio->full_test())
         {
            if (isset($_REQUEST['status']))
            {
               $this->servicio->status = intval($_REQUEST['status']);
               
               if($this->servicio->status == 1 AND is_null($this->servicio->idalbaran))
               {
                  $this->generar_albaran();
               }
               elseif ($this->servicio->save())
               {
                  $this->new_message(ucfirst(FS_SERVICIO)." modificado correctamente.");
               }
               else
               {
                  $this->new_error_msg("¡Imposible modificar el ".FS_SERVICIO."!");
               }
            }
         }
      }
      else
         $this->new_error_msg("¡" . ucfirst(FS_SERVICIO) . " de cliente no encontrado!");
   }

   public function url()
   {
      if (!isset($this->servicio))
      {
         return parent::url();
      }
      else if ($this->servicio)
      {
         return $this->servicio->url();
      }
      else
         return $this->page->url();
   }

   private function modificar()
   {
      $this->servicio->observaciones = $_POST['observaciones'];
      $this->servicio->numero2 = $_POST['numero2'];
      $this->servicio->descripcion = $_POST['descripcion'];
      $this->servicio->solucion = $_POST['solucion'];
      $this->servicio->material = $_POST['material'];
      $this->servicio->estado = $_POST['estado'];
      $this->servicio->accesorios = $_POST['accesorios'];
      

      if (is_null($this->servicio->idalbaran))
      {
         /// obtenemos los datos del ejercicio para acotar la fecha
         $eje0 = $this->ejercicio->get($this->servicio->codejercicio);
         if ($eje0)
         {
            $this->servicio->fecha = $eje0->get_best_fecha($_POST['fecha'], TRUE);
            $this->servicio->hora = $_POST['hora'];
         }
         else
            $this->new_error_msg('No se encuentra el ejercicio asociado al ' . FS_SERVICIO);

         /// ¿cambiamos el cliente?
         if ($_POST['cliente'] != $this->servicio->codcliente)
         {
            $cliente = $this->cliente->get($_POST['cliente']);
            if ($cliente)
            {
               foreach ($cliente->get_direcciones() as $d)
               {
                  if ($d->domfacturacion)
                  {
                     $this->servicio->codcliente = $cliente->codcliente;
                     $this->servicio->cifnif = $cliente->cifnif;
                     $this->servicio->nombrecliente = $cliente->razonsocial;
                     $this->servicio->apartado = $d->apartado;
                     $this->servicio->ciudad = $d->ciudad;
                     $this->servicio->coddir = $d->id;
                     $this->servicio->codpais = $d->codpais;
                     $this->servicio->codpostal = $d->codpostal;
                     $this->servicio->direccion = $d->direccion;
                     $this->servicio->provincia = $d->provincia;
                     break;
                  }
               }
            }
            else
               die('No se ha encontrado el cliente.');
         }
         else
         {
            $this->servicio->codpais = $_POST['codpais'];
            $this->servicio->provincia = $_POST['provincia'];
            $this->servicio->ciudad = $_POST['ciudad'];
            $this->servicio->codpostal = $_POST['codpostal'];
            $this->servicio->direccion = $_POST['direccion'];
            
            $cliente = $this->cliente->get($this->servicio->codcliente);
         }

         $serie = $this->serie->get($this->servicio->codserie);

         /// ¿cambiamos la serie?
         if ($_POST['serie'] != $this->servicio->codserie)
         {
            $serie2 = $this->serie->get($_POST['serie']);
            if ($serie2)
            {
               $this->servicio->codserie = $serie2->codserie;
               $this->servicio->irpf = $serie2->irpf;
               $this->servicio->new_codigo();

               $serie = $serie2;
            }
         }
         
         $this->servicio->codpago = $_POST['forma_pago'];
         
         /// ¿Cambiamos la divisa?
         if($_POST['divisa'] != $this->servicio->coddivisa)
         {
            $divisa = $this->divisa->get($_POST['divisa']);
            if($divisa)
            {
               $this->servicio->coddivisa = $divisa->coddivisa;
               $this->servicio->tasaconv = $divisa->tasaconv;
            }
         }
         else if($_POST['tasaconv'] != '')
         {
            $this->servicio->tasaconv = floatval($_POST['tasaconv']);
         }
         
         if (isset($_POST['numlineas']))
         {
            $numlineas = intval($_POST['numlineas']);

            $this->servicio->neto = 0;
            $this->servicio->totaliva = 0;
            $this->servicio->totalirpf = 0;
            $this->servicio->totalrecargo = 0;
            $lineas = $this->servicio->get_lineas();
            $articulo = new articulo();

            /// eliminamos las líneas que no encontremos en el $_POST
            foreach ($lineas as $l)
            {
               $encontrada = FALSE;
               for ($num = 0; $num <= $numlineas; $num++)
               {
                  if (isset($_POST['idlinea_' . $num]))
                  {
                     if ($l->idlinea == intval($_POST['idlinea_' . $num]))
                     {
                        $encontrada = TRUE;
                        break;
                     }
                  }
               }
               if (!$encontrada)
               {
                  if (!$l->delete())
                     $this->new_error_msg("¡Imposible eliminar la línea del artículo " . $l->referencia . "!");
               }
            }

            /// modificamos y/o añadimos las demás líneas
            for ($num = 0; $num <= $numlineas; $num++)
            {
               $encontrada = FALSE;
               if (isset($_POST['idlinea_' . $num]))
               {
                  foreach ($lineas as $k => $value)
                  {
                     /// modificamos la línea
                     if ($value->idlinea == intval($_POST['idlinea_' . $num]))
                     {
                        $encontrada = TRUE;
                        $lineas[$k]->cantidad = floatval($_POST['cantidad_' . $num]);
                        $lineas[$k]->pvpunitario = floatval($_POST['pvp_' . $num]);
                        $lineas[$k]->dtopor = floatval($_POST['dto_' . $num]);
                        $lineas[$k]->pvpsindto = ($value->cantidad * $value->pvpunitario);
                        $lineas[$k]->pvptotal = ($value->cantidad * $value->pvpunitario * (100 - $value->dtopor) / 100);
                        $lineas[$k]->descripcion = $_POST['desc_' . $num];

                        $lineas[$k]->codimpuesto = NULL;
                        $lineas[$k]->iva = 0;
                        $lineas[$k]->recargo = 0;
                        $lineas[$k]->irpf = $this->servicio->irpf;
                        if (!$serie->siniva AND $cliente->regimeniva != 'Exento')
                        {
                           $imp0 = $this->impuesto->get_by_iva($_POST['iva_' . $num]);
                           if ($imp0)
                              $lineas[$k]->codimpuesto = $imp0->codimpuesto;

                           $lineas[$k]->iva = floatval($_POST['iva_' . $num]);
                           $lineas[$k]->recargo = floatval($_POST['recargo_' . $num]);
                        }

                        if ($lineas[$k]->save())
                        {
                           $this->servicio->neto += $value->pvptotal;
                           $this->servicio->totaliva += $value->pvptotal * $value->iva / 100;
                           $this->servicio->totalirpf += $value->pvptotal * $value->irpf / 100;
                           $this->servicio->totalrecargo += $value->pvptotal * $value->recargo / 100;
                        }
                        else
                           $this->new_error_msg("¡Imposible modificar la línea del artículo " . $value->referencia . "!");
                        break;
                     }
                  }

                  /// añadimos la línea
                  if (!$encontrada AND intval($_POST['idlinea_' . $num]) == -1 AND isset($_POST['referencia_' . $num]))
                  {
                     $linea = new linea_servicio_cliente();
                     $linea->idservicio = $this->servicio->idservicio;
                     $linea->descripcion = $_POST['desc_' . $num];
                     
                     if (!$serie->siniva AND $cliente->regimeniva != 'Exento')
                     {
                        $imp0 = $this->impuesto->get_by_iva($_POST['iva_' . $num]);
                        if($imp0)
                           $linea->codimpuesto = $imp0->codimpuesto;
                        
                        $linea->iva = floatval($_POST['iva_' . $num]);
                        $linea->recargo = floatval($_POST['recargo_' . $num]);
                     }
                     
                     $linea->irpf = floatval($_POST['irpf_'.$num]);
                     $linea->cantidad = floatval($_POST['cantidad_' . $num]);
                     $linea->pvpunitario = floatval($_POST['pvp_' . $num]);
                     $linea->dtopor = floatval($_POST['dto_' . $num]);
                     $linea->pvpsindto = ($linea->cantidad * $linea->pvpunitario);
                     $linea->pvptotal = ($linea->cantidad * $linea->pvpunitario * (100 - $linea->dtopor) / 100);
                     
                     $art0 = $articulo->get($_POST['referencia_' . $num]);
                     if($art0)
                     {
                        $linea->referencia = $art0->referencia;
                     }
                     
                     if( $linea->save() )
                     {
                        $this->servicio->neto += $linea->pvptotal;
                        $this->servicio->totaliva += $linea->pvptotal * $linea->iva / 100;
                        $this->servicio->totalirpf += $linea->pvptotal * $linea->irpf / 100;
                        $this->servicio->totalrecargo += $linea->pvptotal * $linea->recargo / 100;
                     }
                     else
                        $this->new_error_msg("¡Imposible guardar la línea del artículo " . $linea->referencia . "!");
                  }
               }
            }

            /// redondeamos
            $this->servicio->neto = round($this->servicio->neto, FS_NF0);
            $this->servicio->totaliva = round($this->servicio->totaliva, FS_NF0);
            $this->servicio->totalirpf = round($this->servicio->totalirpf, FS_NF0);
            $this->servicio->totalrecargo = round($this->servicio->totalrecargo, FS_NF0);
            $this->servicio->total = $this->servicio->neto + $this->servicio->totaliva - $this->servicio->totalirpf + $this->servicio->totalrecargo;

            if (abs(floatval($_POST['atotal']) - $this->servicio->total) >= .02)
            {
               $this->new_error_msg("El total difiere entre el controlador y la vista (" . $this->servicio->total .
                       " frente a " . $_POST['atotal'] . "). Debes informar del error.");
            }
         }
      }

      if ($this->servicio->save())
      {
         $this->new_message(ucfirst(FS_SERVICIO) . " modificado correctamente.");
         $this->new_change(ucfirst(FS_SERVICIO) . ' Cliente ' . $this->servicio->codigo, $this->servicio->url());
      }
      else
         $this->new_error_msg("¡Imposible modificar el " . FS_SERVICIO . "!");
   }

   private function generar_albaran()
   {
      $albaran = new albaran_cliente();
      $albaran->apartado = $this->servicio->apartado;
      $albaran->automatica = TRUE;
      $albaran->cifnif = $this->servicio->cifnif;
      $albaran->ciudad = $this->servicio->ciudad;
      $albaran->codagente = $this->servicio->codagente;
      $albaran->codalmacen = $this->servicio->codalmacen;
      $albaran->codcliente = $this->servicio->codcliente;
      $albaran->coddir = $this->servicio->coddir;
      $albaran->coddivisa = $this->servicio->coddivisa;
      $albaran->tasaconv = $this->servicio->tasaconv;
      $albaran->codpago = $this->servicio->codpago;
      $albaran->codpais = $this->servicio->codpais;
      $albaran->codpostal = $this->servicio->codpostal;
      $albaran->codserie = $this->servicio->codserie;
      $albaran->direccion = $this->servicio->direccion;
      $albaran->editable = TRUE;
      $albaran->neto = $this->servicio->neto;
      $albaran->nombrecliente = $this->servicio->nombrecliente;
      $albaran->observaciones = $this->servicio->observaciones;
      $albaran->provincia = $this->servicio->provincia;
      $albaran->total = $this->servicio->total;
      $albaran->totaliva = $this->servicio->totaliva;
      $albaran->numero2 = $this->servicio->numero2;
      $albaran->irpf = $this->servicio->irpf;
      $albaran->porcomision = $this->servicio->porcomision;
      $albaran->recfinanciero = $this->servicio->recfinanciero;
      $albaran->totalirpf = $this->servicio->totalirpf;
      $albaran->totalrecargo = $this->servicio->totalrecargo;
      $albaran->idservicio = $this->servicio->idservicio;

      /**
       * Obtenemos el ejercicio para la fecha de hoy (puede que
       * no sea el mismo ejercicio que el del servicio, por ejemplo
       * si hemos cambiado de año)
       */
      $eje0 = $this->ejercicio->get_by_fecha($albaran->fecha);
      $albaran->codejercicio = $eje0->codejercicio;

      $regularizacion = new regularizacion_iva();

      if (!$eje0->abierto())
      {
         $this->new_error_msg("El ejercicio está cerrado.");
      }
      else if ($regularizacion->get_fecha_inside($albaran->fecha))
      {
         $this->new_error_msg("El IVA de ese periodo ya ha sido regularizado. No se pueden añadir más " . FS_ALBARANES . " en esa fecha.");
      }
      else if ($albaran->save())
      {
         $continuar = TRUE;
         $art0 = new articulo();

         foreach ($this->servicio->get_lineas() as $l)
         {
            $n = new linea_albaran_cliente();
            $n->idlineaservicio = $l->idlinea;
            $n->idservicio = $l->idservicio;
            $n->idalbaran = $albaran->idalbaran;
            $n->cantidad = $l->cantidad;
            $n->codimpuesto = $l->codimpuesto;
            $n->descripcion = $l->descripcion;
            $n->dtopor = $l->dtopor;
            $n->irpf = $l->irpf;
            $n->iva = $l->iva;
            $n->pvpsindto = $l->pvpsindto;
            $n->pvptotal = $l->pvptotal;
            $n->pvpunitario = $l->pvpunitario;
            $n->recargo = $l->recargo;
            $n->referencia = $l->referencia;

            if ($n->save())
            {
               /// descontamos del stock
               if( !is_null($n->referencia) )
               {
                  $articulo = $art0->get($n->referencia);
                  if($articulo)
                  {
                     $articulo->sum_stock($albaran->codalmacen, 0 - $l->cantidad);
                  }
               }
            }
            else
            {
               $continuar = FALSE;
               $this->new_error_msg("¡Imposible guardar la línea el artículo " . $n->referencia . "! ");
               break;
            }
         }

         if ($continuar)
         {
            $this->servicio->idalbaran = $albaran->idalbaran;
            $this->servicio->editable = FALSE;
            
            if ($this->servicio->save())
            {
               $this->new_message("<a href='" . $albaran->url() . "'>" . ucfirst(FS_ALBARAN) . '</a> generado correctamente.');
            }
            else
            {
               $this->new_error_msg("¡Imposible vincular el ".FS_SERVICIO." con el nuevo " . FS_ALBARAN . "!");
               if ($albaran->delete())
               {
                  $this->new_error_msg("El " . FS_ALBARAN . " se ha borrado.");
               }
               else
                  $this->new_error_msg("¡Imposible borrar el " . FS_ALBARAN . "!");
            }
         }
         else
         {
            if ($albaran->delete())
            {
               $this->new_error_msg("El " . FS_ALBARAN . " se ha borrado.");
            }
            else
               $this->new_error_msg("¡Imposible borrar el " . FS_ALBARAN . "!");
         }
      }
      else
         $this->new_error_msg("¡Imposible guardar el " . FS_ALBARAN . "!");
   }
}