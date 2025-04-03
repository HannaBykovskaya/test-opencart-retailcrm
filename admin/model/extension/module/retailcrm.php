<?php
class ModelExtensionModuleRetailcrm extends Model {

	public function processHistory() {
		// Получаем последнюю дату синхронизации
		$last_sync = $this->config->get('module_retailcrm_last_history_sync');
		if (!$last_sync) {
			$last_sync = date('Y-m-d H:i:s', strtotime('-1 day'));
		}
	
		// Получаем историю изменений из retailCRM
		$api = $this->getApiClient();
		$response = $api->request->history(array(
			'startDate' => $last_sync,
			'limit' => 100
		));
	
		if ($response->isSuccessful()) {
			$history = $response['history'];
			
			foreach ($history as $change) {
				if ($change['entity'] == 'customer') {
					$this->processCustomerChange($change);
				}
			}
	
			// Обновляем дату последней синхронизации
			$this->model_setting_setting->editSettingValue('module_retailcrm', 'module_retailcrm_last_history_sync', date('Y-m-d H:i:s'));
		}
	}
	
	private function processCustomerChange($change) {
		$customer_id = $change['customer']['id'];
		$source = $change['source'];
		$fields = $change['field'];
		$new_value = $change['newValue'];
		
		// Находим customer_id в OpenCart по externalId
		$this->load->model('customer/customer');
		$customer = $this->model_customer_customer->getCustomerByCustomField('retailcrm_id', $customer_id);
		
		if ($customer) {
			$data = array();
			
			// Обрабатываем изменения полей
			if ($fields == 'firstName' || $fields == 'lastName' || $fields == 'patronymic') {
				$data['firstname'] = $source['firstName'] ?? $customer['firstname'];
				$data['lastname'] = $source['lastName'] ?? $customer['lastname'];
			}
			
			if ($fields == 'email') {
				$data['email'] = $new_value;
			}
			
			if ($fields == 'phones') {
				$data['telephone'] = $new_value[0]['number'] ?? $customer['telephone'];
			}
			
			if ($fields == 'address') {
				$data['address'] = array(
					'address_1' => $new_value['text'] ?? $customer['address_1']
				);
			}
			
			if ($fields == 'managerComment') {
				$data['comment'] = $new_value;
			}
			
			if ($fields == 'customFields' && isset($new_value['children'])) {
				// Обновляем кастомное поле "Количество детей"
				$this->db->query("UPDATE " . DB_PREFIX . "customer SET custom_field = '" . $this->db->escape(json_encode(array('children_count' => $new_value['children_count']))) . "' WHERE customer_id = '" . (int)$customer['customer_id'] . "'");
			}
			
			// Обновляем данные клиента
			if (!empty($data)) {
				$this->model_customer_customer->editCustomer($customer['customer_id'], $data);
			}
		}
	}



	

	private function updateCustomerInDB($customer) {
		$customFields = '';
		if (isset($customer['customFields'])) {
			foreach ($customer['customFields'] as $field) {
				if ($field['name'] == 'children') {
					$customFields = $field['value'];
				}
			}
		}

		$address = isset($customer['address']['street']) ? $this->db->escape($customer['address']['street']) : '';
		$telephone = isset($customer['phones'][0]['number']) ? $this->db->escape($customer['phones'][0]['number']) : '';

		// Запрос на обновление данных в таблице `customer` в базе данных
		$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET 
			firstname = '" . $this->db->escape($customer['firstName']) . "', 
			lastname = '" . $this->db->escape($customer['lastName']) . "', 
			email = '" . $this->db->escape($customer['email']) . "', 
			telephone = '" . $telephone . "', 
			address = '" . $address . "', 
			comment = '" . $this->db->escape($customer['comment']) . "', 
			children = '" . $this->db->escape($customFields) . "' 
			WHERE customer_id = '" . (int)$customer['id'] . "'");

			error_log('Customer data updated for ID: ' . (int)$customer['id']);

	}
}