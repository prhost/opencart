<?php
class ControllerToolBackup extends Controller {
	public function index() {
		$this->load->language('tool/backup');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('tool/backup');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_select_all'] = $this->language->get('text_select_all');
		$data['text_unselect_all'] = $this->language->get('text_unselect_all');

		$data['entry_export'] = $this->language->get('entry_export');
		$data['entry_import'] = $this->language->get('entry_import');

		$data['button_export'] = $this->language->get('button_export');
		$data['button_import'] = $this->language->get('button_import');

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/backup', 'token=' . $this->session->data['token'], true)
		);

		$data['token'] = $this->session->data['token'];

		$data['export'] = $this->url->link('tool/backup/export', 'token=' . $this->session->data['token'], true);

		$data['tables'] = $this->model_tool_backup->getTables();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/backup', $data));
	}
	
	public function import() {
		$this->load->language('tool/backup');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'tool/backup')) {
			$json['error'] = $this->language->get('error_permission');
		}
		
		if (isset($this->request->files['import']['tmp_name'])) {
			$filename = basename(html_entity_decode($this->request->files['import']['tmp_name'], ENT_QUOTES, 'UTF-8'));
		} elseif (isset($this->request->get['import'])) {
			$filename = basename(html_entity_decode($this->request->get['import'], ENT_QUOTES, 'UTF-8'));
		} else {
			$filename = '';
		}
		
		if (!is_file(ini_get('upload_tmp_dir') . '/' . $filename) || substr(str_replace('\\', '/', realpath(ini_get('upload_tmp_dir') . '/' . $filename)), 0, strlen(ini_get('upload_tmp_dir'))) != str_replace('\\', '/', ini_get('upload_tmp_dir'))) {
			$json['error'] = $this->language->get('error_file');
		}	
		
		if (isset($this->request->get['position'])) {
			$position = $this->request->get['position'];
		} else {
			$position = 0; 	
		}
				
		if (!$json) {
			// We set $i so we can batch execute the queries rather than do them all at once.
			$i = 0;
			
			$handle = fopen(ini_get('upload_tmp_dir') . '/' . $filename, 'r');

			fseek($handle, $position, SEEK_SET);
			
			while (!feof($handle) && ($i < 100)) {
				$line = fgets($handle, 1000000);
				
				if (substr($line, 0, 14) == 'TRUNCATE TABLE' || substr($line, 0, 11) == 'INSERT INTO') {
					$sql = '';
					
					$start = true;
				}
				
				if ($start) {
					$sql .= $line;
				}
				
				if ($start && substr($line, -2) == ";\n") {
					$this->db->query(substr($sql, 0, strlen($sql) -2));
					
					$start = false;
				}
					
				$i++;
			}

			$position = ftell($handle);

			$json['success'] = $this->language->get('text_success');
			
			if ($position && !feof($handle)) {
				$json['next'] = str_replace('&amp;', '&', $this->url->link('tool/backup/import', 'token=' . $this->session->data['token'] . '&import=' . $filename . '&position=' . $position));
			}

			fclose($handle);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function export() {
		$this->load->language('tool/backup');

		if (!isset($this->request->post['backup'])) {
			$this->session->data['error'] = $this->language->get('error_export');

			$this->response->redirect($this->url->link('tool/backup', 'token=' . $this->session->data['token'], true));
		} elseif (!$this->user->hasPermission('modify', 'tool/backup')) {
			$this->session->data['error'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('tool/backup', 'token=' . $this->session->data['token'], true));
		} else {
			$this->response->addheader('Pragma: public');
			$this->response->addheader('Expires: 0');
			$this->response->addheader('Content-Description: File Transfer');
			$this->response->addheader('Content-Type: application/octet-stream');
			$this->response->addheader('Content-Disposition: attachment; filename="' . DB_DATABASE . '_' . date('Y-m-d_H-i-s', time()) . '_backup.sql"');
			$this->response->addheader('Content-Transfer-Encoding: binary');

			$this->load->model('tool/backup');

			$this->response->setOutput($this->model_tool_backup->backup($this->request->post['backup']));		
		}
	}	
}
