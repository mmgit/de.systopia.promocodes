<?php
/*-------------------------------------------------------+
| SYSTOPIA PromoCodes Extension                          |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'CRM/Core/Form.php';

use CRM_Promocodes_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Promocodes_Form_Task_Generate extends CRM_Contact_Form_Task
{
    const CUSTOM_FIELD_COUNT = 5;

    public function buildQuickForm()
    {
        CRM_Utils_System::setTitle(E::ts('Promo-Code Generation: Contacts'));

        $this->add(
            'select',
            'campaign_id',
            E::ts('Campaign'),
            CRM_Promocodes_Utils::getCampaignList(),
            true
        );

        $this->add(
            'select',
            'code_type',
            E::ts('Code Type'),
            CRM_Promocodes_Generator::getCodeOptions(),
            true,
            array('class' => 'huge40')
        );

        // add custom field options
        $custom_fields = CRM_Promocodes_Utils::getCustomFields();
        $indices = range(1,self::CUSTOM_FIELD_COUNT);
        $this->assign('custom_indices', $indices);
        foreach ($indices as $i) {
            $this->add(
                'select',
                "custom{$i}_id",
                E::ts('Custom Field %1', [1 => $i]),
                $custom_fields,
                false,
                array('class' => 'huge')
            );
            $this->add(
                'text',
                "custom{$i}_name",
                E::ts('Column Name'),
                array('class' => 'huge'),
                false
            );
        }

        parent::buildQuickForm();

        $this->addButtons(
            array(
                array(
                    'type'      => 'submit',
                    'name'      => E::ts('Generate CSV'),
                    'isDefault' => true,
                ),
                array(
                    'type'      => 'cancel',
                    'name'      => E::ts('Back'),
                    'isDefault' => false,
                ),
            )
        );
    }


    /**
     * get the last iteration's values
     */
    public function setDefaultValues()
    {
        $values = Civi::settings()->get('de.systopia.promocodes.contact');
        if (empty($values) || !is_array($values)) {
            return array();
        } else {
            return $values;
        }
    }

    /**
     * Validate the custom field configs
     */
    public function validate() {
        parent::validate();

        // all fields that are not deactivated need a label
        $indices = range(1,self::CUSTOM_FIELD_COUNT);
        foreach ($indices as $i) {
            if (!empty($this->_submitValues["custom{$i}_id"]) && empty($this->_submitValues["custom{$i}_name"])) {
                $this->_errors["custom{$i}_name"] = E::ts("Please add a column name");
            }
        }
        return (0 == count($this->_errors));
    }

    /**
     * PostProcess:
     *  - store submitted settings as new defaults
     *  - generate CSV
     *
     * @throws CiviCRM_API3_Exception
     */
    public function postProcess()
    {
        // store settings as default
        $all_values = $this->exportValues();

        // store defaults
        $values = array(
            'campaign_id'  => CRM_Utils_Array::value('campaign_id', $all_values),
            'code_type'    => CRM_Utils_Array::value('code_type', $all_values),
            'custom1_id'   => CRM_Utils_Array::value('custom1_id', $all_values),
            'custom1_name' => CRM_Utils_Array::value('custom1_name', $all_values),
        );
        Civi::settings()->set('de.systopia.promocodes.contact', $values);

        // GENERATION:
        if (isset($all_values['_qf_Generate_submit'])) {
            // EXTRACT PARAMETERS
            $params = $values;
            $params['custom_fields'] = [];

            $indices = range(1,self::CUSTOM_FIELD_COUNT);
            foreach ($indices as $i) {
                if (!empty($all_values["custom{$i}_id"]) && !empty($all_values["custom{$i}_name"])) {
                    $params['custom_fields'][] = [
                        'field_id'    => $all_values["custom{$i}_id"],
                        'field_key'   => "custom_field_{$i}",
                        'field_title' => $all_values["custom{$i}_name"],
                    ];
                }
            }

            // CREATE CSV
            $generator = new CRM_Promocodes_Generator($params);
            $generator->generateCSV($all_values['code_type'], $this->_contactIds);
        }

        parent::postProcess();
    }

}
