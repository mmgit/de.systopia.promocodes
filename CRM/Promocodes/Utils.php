<?php
/*-------------------------------------------------------+
| SYSTOPIA PromoCodes Extension                          |
| Copyright (C) 2020 SYSTOPIA                            |
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
 * Generate Promotional Codes
 */
class CRM_Promocodes_Utils {

    /**
     * Get a list of campaigns
     *
     * @return array
     *  list of campaigns
     *
     * @throws Exception
     *  if something goes wrong with the query
     */
    public static function getCampaignList() {
        $campaigns = [
            '0' => E::ts("None")
        ];
        $query = civicrm_api3('Campaign', 'get', [
            'is_active'    => 1,
            'return'       => 'id,title',
            'option.limit' => 0]);
        foreach ($query['values'] as $campaign) {
            $campaigns[$campaign['id']] = $campaign['title'];
        }
        return $campaigns;
    }

    /**
     * Get a list of financial Types
     *
     * @return array
     *  list of campaigns
     *
     * @throws Exception
     *  if something goes wrong with the query
     */
    public static function getFinancialTypes()
    {
        $financial_types = [
            '0' => E::ts("None")
        ];
        $query     = civicrm_api3(
            'FinancialType',
            'get',
            [
                'is_active'    => 1,
                'return'       => 'id,name',
                'option.limit' => 0
            ]
        );
        foreach ($query['values'] as $financial_type) {
            $financial_types[$financial_type['id']] = $financial_type['name'];
        }
        return $financial_types;
    }

    /**
     * Get a list of custom fields that could be used as 'organisation name'
     *
     * @param array $extends
     *  list of entities for which the custom fields should be listed
     *
     * @return array
     *  list of custom fields
     *
     * @throws Exception
     *  if something goes wrong with the query
     */
    public static function getCustomFields($extends = ['Contact', 'Organization', 'Individual', 'Household']) {
        $options = ['' => E::ts("Disabled")];

        // get custom group IDs
        $group_query = civicrm_api3('CustomGroup', 'get', [
            'extends'      => ['IN' => $extends],
            'is_active'    => 1,
            'sequential'   => 0,
            'option.limit' => 0,
            'is_multiple'  => 0,
            'return'       => 'id']);
        $custom_group_ids = array_keys($group_query['values']);

        // get the custom fields
        if ($custom_group_ids) {
            $fields = civicrm_api3('CustomField', 'get', [
                'custom_group_id' => ['IN' => $custom_group_ids],
                'is_active'       => 1,
                'sequential'      => 0,
                'option.limit'    => 0,
                'return'          => 'id,label']);
            foreach ($fields['values'] as $field) {
                $options[$field['id']] = E::ts("Custom: %1", [1 => $field['label']]);
            }
        }

        return $options;
    }

    /**
     * Builds two SQL snippets (selects and joins) to include the custom fields
     *
     * @param array $custom_field_specs
     *    the custom field specs, containing field_id, custom_group_id, field_key
     *
     * @return array
     *    SELECT SQL snippet, JOIN SQL snippet
     *
     * @throws CiviCRM_API3_Exception
     *   should anything go wrong looking up the field metadata
     */
    public static function buildCustomFieldSnippets($custom_field_specs) {
        $CUSTOM_FIELD_SELECTS = '';
        $CUSTOM_FIELD_JOINS   = '';

        if (!empty($custom_field_specs)) {
            $CUSTOM_FIELD_SELECTS = [];
            $CUSTOM_FIELD_JOINS   = [];
            foreach ($custom_field_specs as $field_spec) {
                $field = civicrm_api3('CustomField', 'getsingle', ['id' => $field_spec['field_id']]);
                $group = civicrm_api3('CustomGroup', 'getsingle', ['id' => $field['custom_group_id']]);

                // derive the alias for the referred entity
                // warning: assumes the SQL generator uses aliases like 'contact' and 'membership'...
                switch ($group['extends']) {
                    case 'Contact':
                    case 'Individual':
                    case 'Household':
                    case 'Organization':
                        $table_alias = 'contact';
                        break;

                    case 'Membership':
                        $table_alias = 'membership';
                        break;

                    default:
                        throw new Exception("Unhandled extends entity {$group['extends']} in custom group.");
                }

                $CUSTOM_FIELD_SELECTS[] = "`{$field_spec['field_key']}_table`.`{$field['column_name']}` AS `{$field_spec['field_key']}`";
                $CUSTOM_FIELD_JOINS[]   = "LEFT JOIN {$group['table_name']} `{$field_spec['field_key']}_table` ON `{$field_spec['field_key']}_table`.entity_id = {$table_alias}.id";
            }
            $CUSTOM_FIELD_SELECTS = implode(",\n", $CUSTOM_FIELD_SELECTS) . ',';
            $CUSTOM_FIELD_JOINS   = implode(" \n", $CUSTOM_FIELD_JOINS);
        }
        return [$CUSTOM_FIELD_SELECTS, $CUSTOM_FIELD_JOINS];
    }
}
