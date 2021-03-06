<?php

namespace Cti\Sencha\Generator;

use Cti\Core\String;

class Form extends Generator
{
    /**
     * @var \Cti\Storage\Component\Model
     */
    public $model;

    public function getGeneratedCode()
    {
        $class = $this->model->getClassName();

        $name = $this->model->getName();

        $pk = $this->model->getPk();
        $idProperty = json_encode(count($pk) == 1 ? $pk[0] : $pk);

        $items = array();

        // @todo References only by 1 field
        /**
         * @var \Cti\Storage\Component\Reference[] $referenceByField
         */
        $referenceByField = array();
        foreach($this->model->getOutReferences() as $reference) {
            if (count($reference->getProperties()) == 1) {
                $properties = $reference->getProperties();
                $property = array_shift($properties);
                $referenceByField[$property->getName()] = $reference;
            }
        }

        $item_list = $behaviours = array();

        foreach($this->model->getProperties() as $property) {
            $reference = isset($referenceByField[$property->getName()]) ?
                $referenceByField[$property->getName()] :
                null;
            $item = array(
                'name' => $property->getName(), 
                'allowBlank' => !!$property->getRequired(),
                'fieldLabel' => $property->getComment(),
            );

            if ($reference) {
                $item['xtype'] = 'ctipicker';
                $item['model'] = $reference->getDestination();
                $item['displayField'] = 'name';
                $item['valueField'] = 'id_' . $reference->getDestination();
            } else {
                switch($property->getJavascriptType()) {
                    case 'date':
                        $item['xtype'] = 'datefield';
                        break;
                    case 'numeric':
                        $item['xtype'] = 'numberfield';
                        break;
                    default:
                        $item['xtype'] = 'textfield';
                        break;
                }
            }

            if($property->getBehaviour()) {
                $item['readOnly'] = true;
                $item['disabled'] = true;
                if(!in_array($property->getName(), $pk)) {
                    $behaviours[] = $property->getName();
                }
            } else {
              $item_list[] = $property->getName();
            }

            if($property->getJavascriptType() == 'numeric') {
              $item['xtype'] = 'numberfield';
            } 

            $items[$property->getName()] = $item;
        }
        $item_list = json_encode(array_merge($item_list, $pk, $behaviours));
        $items = json_encode($items);

        $pk_getter = array();
        foreach($pk as $key) {
          $pk_getter[] = $key . ": @" . $key ;
        }
        $pk_getter = implode(', ', $pk_getter);



        return <<<COFFEE
Ext.define 'Generated.Form.$class',

  requires: ['Cti.Picker'],
  extend: 'Ext.form.Panel'
  bodyPadding: 10
  monitorValid: true
  border: false

  getPk: -> $pk_getter

  getItemsConfig: -> $items
  getItemsList: -> $item_list

  modelExists: -> !Ext.Array.contains(Ext.Object.getValues(@getPk()), undefined)

  initComponent: ->
    @items = []
    config = @getItemsConfig()
    for item in @getItemsList()
      @items.push config[item]

    @callParent arguments

    if @modelExists()
      Storage.getModel '$name', @getPk(), (response) =>
        model = Ext.create 'Model.$class', response.data
        @getForm().loadRecord model
        @fireEvent 'recordloaded', model
COFFEE;

    }
}