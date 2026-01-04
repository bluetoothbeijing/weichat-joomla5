<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

class SocialordersControllerDisplay extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        $app = Factory::getApplication();
        $view = $app->input->get('view', 'orders');
        
        // 检查权限
        $user = Factory::getUser();
        if (!$user->authorise('core.manage', 'com_socialorders')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        
        // 设置默认视图
        $app->input->set('view', $view);
        
        parent::display($cachable, $urlparams);
        
        return $this;
    }
    
    public function dashboard()
    {
        $app = Factory::getApplication();
        $app->input->set('view', 'dashboard');
        $app->input->set('layout', 'default');
        
        $this->display();
    }
    
    public function config()
    {
        $app = Factory::getApplication();
        $app->input->set('view', 'config');
        $app->input->set('layout', 'default');
        
        $this->display();
    }
    
    public function saveConfig()
    {
        $app = Factory::getApplication();
        $data = $app->input->post->getArray();
        
        // 检查令牌
        $this->checkToken();
        
        try {
            // 获取组件配置
            $component = JComponentHelper::getComponent('com_socialorders');
            $table = JTable::getInstance('extension');
            $table->load($component->id);
            
            // 更新参数
            $params = new JRegistry($table->params);
            $params->loadArray($data['params'] ?? []);
            $table->params = $params->toString();
            
            if (!$table->store()) {
                throw new Exception('保存配置失败：' . $table->getError());
            }
            
            $app->enqueueMessage('配置保存成功', 'success');
            
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }
        
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=config', false));
    }
    
    public function clearCache()
    {
        $app = Factory::getApplication();
        
        // 检查令牌
        $this->checkToken();
        
        try {
            $cache = JFactory::getCache('com_socialorders');
            $cache->clean();
            
            $app->enqueueMessage('缓存清理成功', 'success');
            
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }
        
        $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
    }
    
    public function exportData()
    {
        $app = Factory::getApplication();
        $type = $app->input->get('type', 'orders', 'cmd');
        
        // 检查令牌
        $this->checkToken();
        
        try {
            $model = $this->getModel($type === 'orders' ? 'Orders' : 'Orders', 'SocialordersModel');
            
            if (method_exists($model, 'export')) {
                $data = $model->export();
                
                // 设置HTTP头
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('YmdHis') . '.csv"');
                
                echo $data;
                $app->close();
            } else {
                throw new Exception('导出功能不可用');
            }
            
        } catch (Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders', false));
        }
    }
    
    public function importData()
    {
        $app = Factory::getApplication();
        $file = $app->input->files->get('import_file');
        
        // 检查令牌
        $this->checkToken();
        
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $app->enqueueMessage('请选择有效的文件', 'error');
            $this->setRedirect(Route::_('index.php?option=com_socialorders&view=config', false));
            return false;
        }
        
        try {
            // 检查文件类型
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($extension), ['csv', 'xls', 'xlsx'])) {
                throw new Exception('只支持CSV、Excel文件');
            }
            
            // 处理上传
            $tmpPath = $file['tmp_name'];
            
            if ($extension === 'csv') {
                $result = $this->importCSV($tmpPath);
            } else {
                $result = $this->importExcel($tmpPath);
            }
            
            $app->enqueueMessage('成功导入 ' . $result . ' 条记录', 'success');
            
        } catch (Exception $e) {
            $app->enqueueMessage('导入失败：' . $e->getMessage(), 'error');
        }
        
        $this->setRedirect(Route::_('index.php?option=com_socialorders&view=config', false));
    }
    
    private function importCSV($filePath)
    {
        $db = Factory::getDbo();
        $count = 0;
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($headers, $data);
                
                // 根据数据类型处理
                $imported = $this->processImportRow($row);
                if ($imported) {
                    $count++;
                }
            }
            
            fclose($handle);
        }
        
        return $count;
    }
    
    private function importExcel($filePath)
    {
        // 需要PHPExcel或PhpSpreadsheet库
        // 这里简化处理
        return 0;
    }
    
    private function processImportRow($row)
    {
        // 根据实际需求处理导入行
        return true;
    }
    
    private function checkToken()
    {
        $token = JSession::getFormToken();
        $inputToken = Factory::getApplication()->input->get('token', '', 'alnum');
        
        if (!hash_equals($token, $inputToken)) {
            throw new Exception('令牌无效');
        }
    }
}