<?php
abstract class FOGPage extends FOGBase {
    public $name = '';
    public $node = '';
    public $id = 'id';
    public $title;
    public $menu = array();
    public $subMenu = array();
    public $notes = array();
    protected $searchFormURL = '';
    protected $titleEnabled = true;
    protected $headerData = array();
    protected $data = array();
    protected $templates = array();
    protected $attributes = array();
    protected static $returnData;
    protected $fieldsToData;
    private $wrapper = 'td';
    private $headerWrap = 'th';
    private $result;
    protected $request = array();
    protected $formAction;
    protected $formPostAction;
    protected $childClass;
    public function __construct($name = '') {
        parent::__construct();
        if (!empty($name)) $this->name = $name;
        $this->title = $this->name;
        $PagesWithObjects = array('user','host','image','group','snapin','printer');
        self::$HookManager->processEvent('PAGES_WITH_OBJECTS',array('PagesWithObjects'=>&$PagesWithObjects));
        if (in_array($this->node,$PagesWithObjects)) {
            $this->childClass = ucfirst($this->node);
            if (isset($_REQUEST['id'])) {
                $this->delformat = "?node={$this->node}&sub=delete&{$this->id}={$_REQUEST['id']}";
                $this->linkformat = "?node={$this->node}&sub=edit&{$this->id}={$_REQUEST['id']}";
                $this->membership = "?node={$this->node}&sub=membership&{$this->id}={$_REQUEST['id']}";
                $this->obj = self::getClass($this->childClass,$_REQUEST['id']);
                if ((int) $_REQUEST['id'] === 0 || !is_numeric($_REQUEST['id']) || !$this->obj->isValid()) {
                    unset($this->obj);
                    $this->setMessage(sprintf(_('%s ID %s is not valid'),$this->childClass,$_REQUEST['id']));
                    $this->redirect(sprintf('?node=%s',$this->node));
                }
            }
            $classVars = self::getClass($this->childClass,'',true);
            $this->databaseTable = $classVars['databaseTable'];
            $this->databaseFields = $classVars['databaseFields'];
            $this->databaseFieldsRequired = $classVars['databaseFieldsRequired'];
            $this->databaseFieldClassRelationships = $classVars['databaseFieldClassRelationships'];
            $this->additionalFields = $classVars['additionalFields'];
            unset($classVars);
        }
        $this->menu = array(
            'search'=>self::$foglang['NewSearch'],
            'list'=>sprintf(self::$foglang['ListAll'],_(sprintf('%ss',$this->childClass))),
            'add'=>sprintf(self::$foglang['CreateNew'],_($this->childClass)),
            'export'=>sprintf(self::$foglang[sprintf('Export%s',$this->childClass)]),
            'import'=>sprintf(self::$foglang[sprintf('Import%s',$this->childClass)]),
        );
        $this->fieldsToData = function(&$input,&$field) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
            if (is_array($this->span) && count($this->span) === 2) $this->data[count($this->data)-1][$this->span[0]] = $this->span[1];
            unset($input);
        };
        $this->formAction = preg_replace('#(&tab.*)$#','',filter_var(html_entity_decode(sprintf('%s?%s',self::$urlself,htmlentities($_SERVER['QUERY_STRING'],ENT_QUOTES,'utf-8'))),FILTER_SANITIZE_URL));
        self::$HookManager->processEvent('SEARCH_PAGES',array('searchPages'=>&self::$searchPages));
        self::$HookManager->processEvent('SUB_MENULINK_DATA',array('menu'=>&$this->menu,'submenu'=>&$this->subMenu,'id'=>&$this->id,'notes'=>&$this->notes));
    }
    public function index() {
        $vals = function(&$value,$key) {
            return sprintf('%s : %s',$key,$value);
        };
        printf('Index page of: %s%s',
            get_class($this),
            (count($args) ? sprintf(', Arguments = %s',implode(', ',array_walk($args,$vals))) : '')
        );
    }
    public function set($key, $value) {
        $this->$key = $value;
        return $this;
    }
    public function get($key) {
        return $this->$key;
    }
    public function __toString() {
        return $this->process();
    }
    public function render() {
        echo $this->process();
    }
    public function process() {
        try {
            unset($actionbox);
            $defaultScreen = strtolower($_SESSION['FOG_VIEW_DEFAULT_SCREEN']);
            $defaultScreens = array('search','list');
            if (!count($this->templates)) throw new Exception(_('Requires templates to process'));
            if (self::$ajax) {
                echo @json_encode(array(
                    'data'=>&$this->data,
                    'templates'=>&$this->templates,
                    'headerData'=>&$this->headerData,
                    'title'=>&$this->title,
                    'attributes'=>&$this->attributes,
                    'form'=>&$this->form,
                    'searchFormURL'=>&$this->searchFormURL,
                ));
                exit;
            }
            ob_start();
            $contentField = 'active-tasks';
            if ($this->searchFormURL) {
                printf('<form method="post" action="%s" id="search-wrapper"><input id="%s-search" class="search-input placeholder" type="text" value="" placeholder="%s" autocomplete="off" %s/><%s id="%s-search-submit" class="search-submit" type="%s" value="%s"></form>%s',
                    $this->searchFormURL,
                    (substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),
                    sprintf('%s %s', ucwords((substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node)), self::$foglang['Search']),
                    self::$isMobile ? 'name="host-search"' : '',
                    self::$isMobile ? 'input' : 'button',
                    (substr($this->node, -1) == 's' ? substr($this->node, 0, -1) : $this->node),
                    self::$isMobile ? 'submit' : 'button',
                    self::$isMobile ? self::$foglang['Search'] : '',
                    self::$isMobile ? '</input>' : '</button>'
                );
                $contentField = 'search-content';
            }
            if (isset($this->form)) printf($this->form);
            printf('<table width="%s" cellpadding="0" cellspacing="0" border="0" id="%s">%s<tbody>',
                '100%',
                $contentField,
                $this->buildHeaderRow()
            );
            $node = htmlentities($_REQUEST['node'],ENT_QUOTES,'utf-8');
            $sub = htmlentities($_REQUEST['sub'],ENT_QUOTES,'utf-8');
            if (!count($this->data)) {
                $contentField = 'no-active-tasks';
                printf('<tr><td colspan="%s" class="%s">%s</td></tr></tbody></table>',
                    count($this->templates),
                    $contentField,
                    ($this->data['error'] ? (is_array($this->data['error']) ? sprintf('<p>%s</p>',implode('</p><p>',$this->data['error'])) : $this->data['error']) : ($this->node != 'task' ? (!self::$isMobile ? self::$foglang['NoResults'] : '') : self::$foglang['NoResults']))
                );
            } else {
                if ((!$sub && $defaultScreen == 'list') || (in_array($sub,$defaultScreens) && in_array($node,self::$searchPages)))
                    if ($this->node != 'home') $this->setMessage(sprintf('%s %s%s found',count($this->data),$this->childClass,(count($this->data) != 1 ? 's' : '')));
                $id_field = "{$node}_id";
                array_map(function(&$rowData) use ($id_field) {
                    printf('<tr id="%s-%s">%s</tr>',
                        strtolower($this->childClass),
                        (isset($rowData['id']) ? $rowData['id'] : (isset($rowData[$id_field]) ? $rowData[$id_field] : '')),
                        $this->buildRow($rowData)
                    );
                },(array)$this->data);
            }
            echo '</tbody></table>';
            if (((!$sub || ($sub && in_array($sub,$defaultScreens))) && in_array($node,self::$searchPages)) && !self::$isMobile) {
                if ($this->node == 'host') {
                    printf('<form method="post" action="%s", id="action-box"><input type="hidden" name="hostIDArray" value="" autocomplete="off"/><p><label for="group_new">%s</label><input type="text" name="group_new" id="group_new" autocomplete="off"/></p><p class="c">OR</p><p><label for="group">%s</label>%s</p><p class="c"><input type="submit" value="%s"/></p></form>',
                        sprintf('?node=%s&sub=save_group',$this->node),
                        _('Create new group'),
                        _('Add to group'),
                        self::getClass('GroupManager')->buildSelectBox(),
                        _('Process Group Changes')
                    );
                }
                if ($this->node != 'task') {
                    printf('<form method="post" class="c" id="action-boxdel" action="%s"><p>%s</p><input type="hidden" name="%sIDArray" value="" autocomplete="off"/><input type="submit" value="%s?"/></form>',
                        sprintf('?node=%s&sub=deletemulti',$this->node),
                        _('Delete all selected items'),
                        strtolower($this->node),
                        sprintf(_('Delete all selected %ss'),strtolower($this->node))
                    );
                }
            }
            self::$HookManager->processEvent('ACTIONBOX',array('actionbox'=>&$actionbox));
            return ob_get_clean();
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $result;
    }
    private function setAtts() {
        array_walk($this->attributes,function(&$attribute,$index) {
            array_walk($attribute,function(&$val, $name) use ($index) {
                $this->atts[$index] .= sprintf(' %s="%s" ',
                    $name,
                    ($this->dataFind ? preg_replace($this->dataFind,$this->dataReplace,$val) : $val)
                );
            });
        });
    }
    public function buildHeaderRow() {
        unset($this->atts);
        $this->setAtts();
        if (!$this->headerData) return;
        $setHeaderData = function(&$content,$index) {
            printf('<%s%s data-column="%s">%s</%s>',
                $this->headerWrap,
                ($this->atts[$index] ? $this->atts[$index] : ''),
                $index,
                $content,
                $this->headerWrap
            );
        };
        ob_start();
        printf('<thead%s><tr class="header">',count($this->data) < 1 ? ' class="hidden"' : '');
        array_walk($this->headerData,$setHeaderData);
        echo '</tr></thead>';
        return ob_get_clean();
    }
    private function replaceNeeds($data) {
        unset($this->dataFind,$this->dataReplace);
        $urlvars = array('node'=>$GLOBALS['node'],'sub'=>$GLOBALS['sub'],'tab'=>$GLOBALS['tab']);
        preg_match_all('#\$\{(.+?)\}#',implode($this->templates),$foundchanges);
        $arrayReplace = array_merge($urlvars,(array)$data);
        $foundchanges = $foundchanges[1];
        array_map(function(&$name) use ($arrayReplace) {
            $this->dataFind[] = sprintf('#\$\{%s\}#',$name);
            $this->dataReplace[] = isset($arrayReplace[$name]) ? $arrayReplace[$name] : '';
        },(array)$foundchanges);
        array_walk($arrayReplace,function(&$val,$name) {
            $this->dataFind[] = sprintf('#\$\{%s\}#',$name);
            $this->dataReplace[] = isset($val) ? $val : '';
        });
    }
    public function buildRow($data) {
        unset($this->atts);
        $this->replaceNeeds($data);
        $this->setAtts();
        ob_start();
        array_walk($this->templates,function(&$template,$index) {
            printf('<%s%s>%s</%s>',
                $this->wrapper,
                $this->atts[$index] ? $this->atts[$index] : '',
                preg_replace($this->dataFind,$this->dataReplace,$template),
                $this->wrapper
            );
        });
        return ob_get_clean();
    }
    public function deploy() {
        try {
            $TaskType = self::getClass('TaskType',(is_numeric($_REQUEST['type']) && (int) $_REQUEST['type'] ? (int) $_REQUEST['type'] : 1));
            $imagingTypes = in_array($TaskType->get('id'),array(1,2,8,15,16,17,24));
            if (($this->obj instanceof Group && !(count($this->obj->get('hosts')))) || ($this->obj instanceof Host && ($this->obj->get('pending') || !$this->obj->isValid())) || (!($this->obj instanceof Host || $this->obj instanceof Group))) throw new Exception(_('Cannot set taskings to pending or invalid items'));
            if ($imagingTypes && $this->obj instanceof Host && !$this->obj->getImage()->get('isEnabled')) throw new Exception(_('Cannot set tasking as image is not enabled'));
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect(sprintf('?node=%s&sub=edit%s',$this->node,(is_numeric($_REQUEST['id']) && (int) $_REQUEST['id'] > 0 ? sprintf('&%s=%s',$this->id,(int) $_REQUEST['id']) : '')));
        }
        $this->title = sprintf('%s %s %s %s',_('Create'),$TaskType->get('name'),_('task for'),$this->obj->get('name'));
        printf('<p class="c"><b>%s</b></p>',_('Are you sure you wish to deploy task to these machines'));
        printf('<form method="post" action="%s" id="deploy-container">',$this->formAction);
        echo '<div class="confirm-message">';
        if ($TaskType->get('id') == 13) {
            printf('<p class="c"><p>%s</p>',_('Please select the snapin you want to deploy'));
            if ($this->obj instanceof Host) {
                foreach((array)$this->obj->get('snapins') AS $i => &$id) {
                    $name = self::getClass('Snapin',$id)->get('name');
                    $optionSnapin .= sprintf('<option value="%s">%s - (%s)</option>',$id,$name,$id);
                }
                unset($id);
                if ($optionSnapin) printf('<select name="snapin">%s</select></p>',$optionSnapin);
                else printf('%s</p>',_('No snapins associated'));
            }
            if ($this->obj instanceof Group) printf('%s</p>',self::getClass('SnapinManager')->buildSelectBox('','snapin'));
        }
        printf('<div class="advanced-settings"><h2>%s</h2>',_('Advanced Settings'));
        if ($TaskType->isInitNeededTasking() && !$TaskType->isDebug()) printf('<p class="hideFromDebug"><input type="checkbox" name="shutdown" id="shutdown" value="1" autocomplete="off"><label for="shutdown">%s <u>%s</u> %s</label></p>',_('Schedule'),_('Shutdown'),_('after task completion'));
        if (!$TaskType->isDebug() && $TaskType->get('id') != 11) {
            if ($TaskType->isInitNeededTasking() && !($this->obj instanceof Group)) printf('<p><input type="checkbox" name="isDebugTask" id="checkDebug"><label for="checkDebug">%s</label></p>',_('Schedule task as a debug task'));
            printf('<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked/><label for="scheduleInstant">%s <u>%s</u></label></p>',_('Schedule'),_('Instant Deployment'));
            printf('<p><input type="radio" name="scheduleType" id="scheduleSingle" value="single" autocomplete="off"/><label for="scheduleSingle">%s <u>%s</u></label></p>',_('Schedule'),_('Delayed Deployment'));
            echo '<p class="hidden hideFromDebug" id="singleOptions"><input type="text" name="scheduleSingleTime" id="scheduleSingleTime" autocomplete="off"/></p>';
            printf('<p><input type="radio" name="scheduleType" id="scheduleCron" value="cron" autocomplete="off"><label for="scheduleCron">%s <u>%s</u></label></p>',_('Schedule'),_('Cron-style Deployment'));
            echo '<p class="hidden hideFromDebug" id="cronOptions">';
            $specialCrons = array(
                ''=>_('Select a cron type'),
                'yearly'=>sprintf('%s/%s',_('Yearly'),_('Annually')),
                'monthly'=>_('Monthly'),
                'weekly'=>_('Weekly'),
                'daily'=>sprintf('%s/%s',_('Daily'),_('Midnight')),
                'hourly'=>_('Hourly'),
            );
            ob_start();
            foreach($specialCrons AS $val => &$name) {
                printf('<option value="%s">%s</option>',$val,$name);
                unset($name);
            }
            printf('<select id="specialCrons" name="specialCrons">%s</select><br/><br/>',ob_get_clean());
            echo '<input type="text" name="scheduleCronMin" id="scheduleCronMin" placeholder="min" autocomplete="off"/>';
            echo '<input type="text" name="scheduleCronHour" id="scheduleCronHour" placeholder="hour" autocomplete="off"/>';
            echo '<input type="text" name="scheduleCronDOM" id="scheduleCronDOM" placeholder="dom" autocomplete="off"/>';
            echo '<input type="text" name="scheduleCronMonth" id="scheduleCronMonth" placeholder="month" autocomplete="off"/>';
            echo '<input type="text" name="scheduleCronDOW" id="scheduleCronDOW" placeholder="dow" autocomplete="off" /></p>';
        } else if ($TaskType->isDebug() || $TaskType->get('id') == 11) printf('<p><input type="radio" name="scheduleType" id="scheduleInstant" value="instant" autocomplete="off" checked/><label for="scheduleInstant">%s <u>%s</u></label></p>',_('Schedule'),_('Instant Deployment'));
        if ($TaskType->get('id') == 11) {
            printf("<p>%s</p>",_('Which account would you like to reset the pasword for'));
            echo '<input type="text" name="account" value="Administrator"/>';
        }
        printf('</div></div><h2>%s</h2>',_('Hosts in Task'));
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
            array(),
        );
        $this->templates = array(
            '<a href="${host_link}" title="${host_title}">${host_name}</a>',
            '${host_mac}',
            '<a href="${image_link}" title="${image_title}">${image_name}</a>',
        );
        if ($this->obj instanceof Host) {
            $this->data[] = array(
                'host_link'=>'?node=host&sub=edit&id=${host_id}',
                'image_link'=>'?node=image&sub=edit&id=${image_id}',
                'host_id'=>$this->obj->get('id'),
                'image_id'=>$this->obj->getImage()->get('id'),
                'host_name'=>$this->obj->get('name'),
                'host_mac'=>$this->obj->get('mac'),
                'image_name'=>$this->obj->getImage()->get('name'),
                'host_title'=>_('Edit Host'),
                'image_title'=>_('Edit Image'),
            );
        }
        if ($this->obj instanceof Group) {
            foreach(self::getClass('HostManager')->find(array('id'=>$this->obj->get('hosts'))) AS $i => &$Host) {
                if (!$Host->isValid()) continue;
                $this->data[] = array(
                    'host_link'=>'?node=host&sub=edit&id=${host_id}',
                    'image_link'=>'?node=image&sub=edit&id=${image_id}',
                    'host_id'=>$Host->get('id'),
                    'image_id'=>$Host->getImage()->get('id'),
                    'host_name'=>$Host->get('name'),
                    'host_mac'=>$Host->get('mac'),
                    'image_name'=>$Host->getImage()->get('name'),
                    'host_title'=>_('Edit Host'),
                    'image_title'=>_('Edit Image'),
                );
                unset($Host);
            }
        }
        self::$HookManager->processEvent(sprintf('%s_DEPLOY',strtoupper($this->childClass)),array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        if (count($this->data)) printf('<p class="c"><input type="submit" value="%s"/></p>',$this->title);
        echo '</form>';
    }
    public function deploy_post() {
        try {
            $TaskType = self::getClass('TaskType',((int) $_REQUEST['type'] ? (int) $_REQUEST['type'] : 1));
            $imagingTypes = in_array($TaskType->get('id'),array(1,2,8,15,16,17,24));
            if (($this->obj instanceof Group && !(count($this->obj->get('hosts')))) || ($this->obj instanceof Host && ($this->obj->get('pending') || !$this->obj->isValid())) || (!($this->obj instanceof Host || $this->obj instanceof Group))) throw new Exception(_('Cannot set taskings to pending or invalid items'));
            if ($imagingTypes && $this->obj instanceof Host && !$this->obj->getImage()->get('isEnabled')) throw new Exception(_('Cannot set tasking as image is not enabled'));
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect(sprintf('?node=%s&sub=edit%s',$this->node,(is_numeric($_REQUEST['id']) && (int) $_REQUEST['id'] > 0 ? sprintf('&%s=%s',$this->id,(int) $_REQUEST['id']) : '')));
        }
        $Snapin = self::getClass('Snapin',(int) $_REQUEST['snapin']);
        $enableShutdown = $_REQUEST['shutdown'] ? true : false;
        $enableSnapins = $TaskType->get('id') != 17 ? ($Snapin instanceof Snapin && $Snapin->isValid() ? $Snapin->get('id') : -1) : false;
        $enableDebug = (bool)((isset($_REQUEST['debug']) && $_REQUEST['debug'] == 'true') || isset($_REQUEST['isDebugTask']));
        $scheduleDeployTime = $this->nice_date($_REQUEST['scheduleSingleTime']);
        $imagingTasks = in_array($TaskType->get('id'),array(1,2,8,15,16,17,24));
        $passreset = trim(htmlentities($_REQUEST['account'],ENT_QUOTES,'utf-8'));
        try {
            if (!$TaskType || !$TaskType->isValid()) throw new Exception(_('Task type is not valid'));
            $taskName = sprintf('%s Task',$TaskType->get('name'));
            if ($this->obj->isValid()) {
                if ($this->obj instanceof Host && $imagingTasks) {
                    if(!$this->obj->getImage() || !$this->obj->getImage()->isValid()) throw new Exception(_('You need to assign an image to the host'));
                    if ($TaskType->isUpload() && $this->obj->getImage()->get('protected')) throw new Exception(_('You cannot upload to this image as it is currently protected'));
                    $this->obj->checkIfExist($TaskType->get('id'));
                } else if ($this->obj instanceof Group && $imagingTasks) {
                    if ($TaskType->isMulticast() && !$this->obj->doMembersHaveUniformImages()) throw new Exception(_('Hosts do not contain the same image assignments'));
                    foreach(self::getClass('HostManager')->find(array('pending'=>array('',0),'id'=>$this->obj->get('hosts'))) AS &$Host) {
                        if (!$Host->isValid()) continue;
                        $NoImage[] = (bool)!$Host->getImage()->isValid();
                        unset($Host);
                    }
                    if (in_array(true,$NoImage,true)) throw new Exception(_('One or more hosts do not have an image set'));
                    foreach(self::getClass('HostManager')->find(array('pending'=>array('',0),'id'=>$this->obj->get('hosts'))) AS &$Host) {
                        if (!$Host->isValid()) continue;
                        $ImageExists[] = (bool)!$Host->checkIfExist($TaskType->get('id'));
                        unset($Host);
                    }
                    if (in_array(true,$ImageExists,true)) throw new Exception(_('One or more hosts have an image that does not exist'));
                    unset($NoImage,$ImageExists,$Tasks);
                }
                if ($TaskType->get('id') == 11 && empty($passreset)) throw New Exception(_('Password reset requires a user account to reset'));
                try {
                    $groupTask = $this->obj instanceof Group;
                    switch ($_REQUEST['scheduleType']) {
                    case 'instant':
                        $success = $this->obj->createImagePackage($TaskType->get('id'),$taskName,$enableShutdown,$enableDebug,$enableSnapins,$groupTask,$_SESSION['FOG_USERNAME'],$passreset);
                        if (!is_array($success)) $success = array($success);
                        break;
                    case 'single':
                        if ($scheduleDeployTime < $this->nice_date()) throw new Exception(sprintf('%s<br>%s: %s',_('Scheduled date is in the past'),_('Date'),$scheduleDeployTime->format('Y-m-d H:i:s')));
                        break;
                    }
                    if (in_array($_REQUEST['scheduleType'],array('single','cron'))) {
                        $ScheduledTask = self::getClass('ScheduledTask')
                            ->set('taskType',$TaskType->get('id'))
                            ->set('name',$taskName)
                            ->set('hostID',$this->obj->get('id'))
                            ->set('shutdown',$enableShutdown)
                            ->set('other2',$enableSnapins)
                            ->set('type',($_REQUEST['scheduleType'] == 'single' ? 'S' : 'C'))
                            ->set('isGroupTask',$groupTask)
                            ->set('other3',$_SESSION['FOG_USERNAME'])
                            ->set('isActive',1);
                        if ($_REQUEST['scheduleType'] == 'single') $ScheduledTask->set('scheduleTime',$scheduleDeployTime->getTimestamp());
                        else if ($_REQUEST['scheduleType'] == 'cron') {
                            $valsToTest = array(
                                'checkMinutesField' => array('minute',$_REQUEST['scheduleCronMin']),
                                'checkHoursField' => array('hour',$_REQUEST['scheduleCronHour']),
                                'checkDOMField' => array('dayOfMonth',$_REQUEST['scheduleCronDOM']),
                                'checkMonthField' => array('month',$_REQUEST['scheduleCronMonth']),
                                'checkDOWField' => array('dayOfWeek',$_REQUEST['scheduleCronDOW']),
                            );
                            foreach ($valsToTest AS $func => &$val) {
                                if (!FOGCron::$func($val[1])) throw new Exception(sprintf('%s %s invalid',$func,$val[1]));
                                else $ScheduledTask->set($val[0],$val[1]);
                                unset($val);
                            }
                            unset($valsToTest);
                            $ScheduledTask->set('isActive',1);
                        }
                        if ($ScheduledTask->save()) {
                            if ($this->obj instanceof Group) {
                                $Hosts = self::getClass('HostManager')->find(array('id'=>$this->obj->get('hosts')));
                                foreach($Hosts AS $i => &$Host) {
                                    if ($Host->isValid() && !$Host->get('pending')) {
                                        if (!$imagingTasks) $success[] = sprintf('<li>%s</li>',$Host->get('name'));
                                        else if ($imagingTasks && $Host->getImage()->get('isEnabled')) $success[] = sprintf('<li>%s &ndash; %s</li>',$Host->get('name'),$Host->getImage()->get('name'));
                                    }
                                }
                                unset($Host);
                            } else if ($this->obj instanceof Host) {
                                if ($this->obj->isValid() && !$this->obj->get('pending')) $success[] = sprintf('<li>%s &ndash; %s</li>',$this->obj->get('name'),$this->obj->getImage()->get('name'));
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error[] = sprintf('%s %s<br/>%s',$this->obj->get('name'),_('Failed to start deployment tasking'),$e->getMessage());
                }
            }
            if (count($error)) throw new Exception(sprintf('<ul><li>%s</li></ul>',implode('</li><li>',$error)));
        } catch (Exception $e) {
            printf('<div class="task-start-failed"><p>%s</p><p>%s</p></div>',_('Failed to create deployment tasking for the following hosts'),$e->getMessage());
        }
        if (count($success)) {
            if ($_REQUEST['scheduleType'] == 'cron') $time = sprintf('%s: %s',_('Cron Schedule'),implode(' ',array($_REQUEST['scheduleCronMin'],$_REQUEST['scheduleCronHour'],$_REQUEST['scheduleCronDOM'],$_REQUEST['scheduleCronMonth'],$_REQUEST['scheduleCronDOW'])));
            else if ($_REQUEST['scheduleType'] == 'single') $time = sprintf('%s: %s',_('Delayed Start'), $scheduleDeployTime->format('Y-m-d H:i:s'));
            printf('<div class="task-start-ok"><p>%s</p><p>%s%s</p></div>',
                _('Successfully created tasks for deployment to the following Hosts'),
                $time,
                (count($success) ? sprintf('<ul>%s</ul>',implode('',$success)) : '')
            );
        }
    }
    public function deletemulti() {
        $this->title = _(sprintf("%s's to remove",$this->childClass));
        unset($this->headerData);
        $this->attributes = array(
            array(),
        );
        $this->templates = array(
            sprintf('<a href="?node=%s&sub=edit&id=${id}">${name}</a>',$this->node),
            '<input type="hidden" value="${id}" name="remitems[]"/>',
        );
        $this->additional = array();
        foreach ((array)self::getClass($this->childClass)->getManager()->find(array('id'=>array_filter(array_unique(explode(',',$_REQUEST[sprintf('%sIDArray',$this->node)]))))) AS $i => &$Object) {
            if ($Object->get('protected')) continue;
            $this->data[] = array(
                'id'=>$Object->get('id'),
                'name'=>$Object->get('name'),
            );
            array_push($this->additional,sprintf('<p>%s</p>',$Object->get('name')));
            unset($Object,$name);
        }
        if (count($this->data)) {
            printf('<div class="confirm-message"><p>%s\'s %s:</p><form method="post" action="%s_conf">',$this->childClass,_('to be removed'),$this->formAction);
            $this->render();
            printf('<p class="c"><input type="submit" value="%s?"/></p></form></div>',_('Are you sure you wish to remove these items'));
        } else {
            $this->setMessage(sprintf('%s<br/>%s',_('No items to delete'),_('None selected or item is protected')));
            $this->redirect(sprintf('?node=%s',$this->node));
        }
    }
    public function deletemulti_conf() {
        self::$HookManager->processEvent('MULTI_REMOVE',array('removing'=>&$_REQUEST['remitems']));
        self::getClass($this->childClass)->getManager()->destroy(array('id'=>$_REQUEST['remitems']));
        $this->setMessage(_('All selected items have been deleted'));
        $this->redirect(sprintf('?node=%s',$this->node));
    }
    public function basictasksOptions() {
        unset($this->headerData);
        $this->templates = array(
            sprintf('<a href="?node=${node}&sub=${sub}&id=${%s_id}${task_type}"><i class="fa fa-${task_icon} fa-3x"></i><br/>${task_name}</a>',$this->node),
            '${task_desc}',
        );
        $this->attributes = array(
            array('class' => 'l'),
            array('style' => 'padding-left: 20px'),
        );
        printf("<!-- Basic Tasks -->");
        printf('<!-- Basic Tasks --><div id="%s-tasks"><h2>%s %s</h2>',$this->node,$this->childClass,_('Tasks'));
        foreach ((array)self::getClass('TaskTypeManager')->find(array('access'=>array('both',$this->node),'isAdvanced'=>0),'AND','id') AS $i => &$TaskType) {
            if (!$TaskType->isValid()) continue;
            $this->data[] = array(
                'node'=>$this->node,
                'sub'=>'deploy',
                sprintf('%s_id',$this->node) => $this->obj->get('id'),
                'task_type' => sprintf('&type=%s',$TaskType->get('id')),
                'task_icon' => $TaskType->get('icon'),
                'task_name' => $TaskType->get('name'),
                'task_desc' => $TaskType->get('description'),
            );
            unset($TaskType);
        }
        $this->data[] = array(
            'node' => $this->node,
            'sub' => 'edit',
            sprintf('%s_id',$this->node) => $this->obj->get('id'),
            'task_type' => sprintf('#%s-tasks" class="advanced-tasks-link',$this->node),
            'task_icon' => 'bars',
            'task_name' => _('Advanced'),
            'task_desc' => sprintf('%s %s',_('View advanced tasks for this'),$this->node),
        );
        self::$HookManager->processEvent(sprintf('%s_EDIT_TASKS',strtoupper($this->childClass)), array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        unset($this->data);
        printf('<div id="advanced-tasks" class="hidden"><h2>%s</h2>',_('Advanced Actions'));
        $TaskTypes = self::getClass('TaskTypeManager')->find(array('access'=>array('both',$this->node),'isAdvanced'=>1),'AND','id');
        foreach(self::getClass('TaskTypeManager')->find(array('access'=>array('both',$this->node),'isAdvanced'=>1),'AND','id') AS $i => &$TaskType) {
            if (!$TaskType->isValid()) continue;
            $this->data[] = array(
                'node'=>$this->node,
                'sub'=>'deploy',
                sprintf('%s_id',$this->node)=>$this->obj->get('id'),
                'task_type'=>sprintf('&type=%s',$TaskType->get('id')),
                'task_icon'=>$TaskType->get('icon'),
                'task_name'=>$TaskType->get('name'),
                'task_desc'=>$TaskType->get('description'),
            );
            unset($TaskType);
        }
        self::$HookManager->processEvent(sprintf('%s_DATA_ADV',strtoupper($this->node)), array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</div></div>';
        unset($this->data);
    }
    public function adFieldsToDisplay($useAD = '',$ADDomain = '',$ADOU = '',$ADUser = '',$ADPass = '',$ADPassLegacy = '',$enforce = '') {
        unset($this->data,$this->headerData,$this->templates,$this->attributes);
        if (empty($useAD)) $useAD = ($this->obj instanceof Host ? $this->obj->get('useAD') : $_REQUEST['domain']);
        if (empty($ADDomain)) $ADDomain = ($this->obj instanceof Host ? $this->obj->get('ADDomain') : $_REQUEST['domainname']);
        if (empty($ADOU)) $ADOU = trim(preg_replace('#;#','',($this->obj instanceof Host ? $this->obj->get('ADOU') : htmlentities($_REQUEST['ou'],ENT_QUOTES,'utf-8'))));
        if (empty($ADUser)) $ADUser = ($this->obj instanceof Host ? $this->obj->get('ADUser') : $_REQUEST['domainuser']);
        if (empty($ADPass)) $ADPass = ($this->obj instanceof Host ? $this->obj->get('ADPass') : $_REQUEST['domainpassword']);
        if (empty($ADPassLegacy)) $ADPassLegacy = ($this->obj instanceof Host ? $this->obj->get('ADPassLegacy') : $_REQUEST['domainpasswordlegacy']);
        if (empty($enforce)) $enforce = ($this->obj instanceof Host ? $this->obj->get('enforce') : (!isset($_REQUEST['enforce']) ? (int)$this->getSetting('FOG_ENFORCE_HOST_CHANGES') : $_REQUEST['enforcesel']));
        $OUs = explode('|',$this->getSetting('FOG_AD_DEFAULT_OU'));
        foreach((array)$OUs AS $i => &$OU) $OUOptions[] = $OU;
        unset($OU);
        $OUOPtions = array_filter($OUOptions);
        if (count($OUOptions) > 1) {
            $OUs = array_unique((array)$OUOptions);
            $optFound = false;
            foreach ($OUs AS &$OU) {
                if (!$optFound && preg_match('#;#i',$OU)) {
                $optFound = trim(preg_replace('#;#','',$OU));
                unset($OU);
                break;
                }
                unset($OU);
            }
            $OUOrig = $OUs;
            if (!$optFound && !$ADOU) $optNotFound = trim(preg_replace('#;#','',array_pop($OUs)));
            $OUs = $OUOrig;
            ob_start();
            printf('<option value="">- %s -</option>',_('Please select an option'));
            foreach($OUs AS $i => &$OU) {
                $OU = trim(preg_replace('#;#','',$OU));
                printf('<option value="%s"%s>%s</option>',$OU,(($ADOU == $OU) || ($optFound && !$ADOU && $OU == $optFound) || (!$optFound && !$ADOU && $optNotFound == $OU)? 'selected' : ''),$OU);
            }
            unset($OUs);
            $OUOptions = sprintf('<select id="adOU" class="smaller" name="ou">%s</select>',ob_get_clean());
        } else $OUOptions = sprintf('<input id="adOU" class="smaller" type="text" name="ou" value="%s" autocomplete="off"/>',$ADOU);
        echo '<!-- Active Directory -->';
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            '<input style="display:none" type="text" name="fakeusernameremembered"/>' => '<input style="display:none" type="password" name="fakepasswordremembered"/>',
            _('Join Domain after image task') => sprintf('<input id="adEnabled" type="checkbox" name="domain"%s/>',$useAD ? ' checked' : ''),
            _('Domain name') => sprintf('<input id="adDomain" class="smaller" type="text" name="domainname" value="%s" autocomplete="off"/>',$ADDomain),
            sprintf('%s<br/><span class="lightColor">(%s)</span>',_('Organizational Unit'),_('Blank for default')) => $OUOptions,
            _('Domain Username') => sprintf('<input id="adUsername" class="smaller" type="text"name="domainuser" value="%s" autocomplete="off"/>',$ADUser),
            sprintf('%s<br/>(%s)',_('Domain Password'),_('Will auto-encrypt plaintext')) => sprintf('<input id="adPassword" class="smaller" type="password" name="domainpassword" value="%s" autocomplete="off"/>',$ADPass),
            sprintf('%s<br/>(%s)',_('Domain Password Legacy'),_('Must be encrypted')) => sprintf('<input id="adPasswordLegacy" class="smaller" type="password" name="domainpasswordlegacy" value="%s" autocomplete="off"/>',$ADPassLegacy),
            sprintf('%s',_('Host changes every cycle')) => sprintf('<input name="enforcesel" type="checkbox" autocomplete="off"%s/><input type="hidden" name="enforce"/>',$enforce ? ' checked' : ''),
            '&nbsp;' => sprintf('<input name="updatead" type="submit" value="%s"/>',($_REQUEST['sub'] == 'add' ? _('Add') : _('Update'))),
        );
        printf('<div id="%s-active-directory"><form method="post" action="%s&tab=%s-active-directory"><h2>%s<div id="adClear"></div></h2>',$this->node,$this->formAction,$this->node,_('Active Directory'));
        foreach((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }
        unset($input);
        self::$HookManager->processEvent(strtoupper($this->childClass).'_EDIT_AD', array('headerData' => &$this->headerData,'data' => &$this->data,'attributes' => &$this->attributes,'templates' => &$this->templates));
        $this->render();
        unset($this->data);
        echo '</form></div>';
    }
    public function adInfo() {
        $Data = array(
            'domainname' => $this->getSetting('FOG_AD_DEFAULT_DOMAINNAME'),
            'ou' => $this->getSetting('FOG_AD_DEFAULT_OU'),
            'domainuser' => $this->getSetting('FOG_AD_DEFAULT_USER'),
            'domainpass' => $this->encryptpw($this->getSetting('FOG_AD_DEFAULT_PASSWORD')),
            'domainpasslegacy' => $this->getSetting('FOG_AD_DEFAULT_PASSWORD_LEGACY'),
        );
        if (self::$ajax) echo json_encode($Data);
    }
    public function kernelfetch() {
        try {
            if (!$_SESSION['AllowAJAXTasks']) throw new Exception(_('FOG Session Invalid'));
            if ($_SESSION['allow_ajax_kdl'] && $_SESSION['dest-kernel-file'] && $_SESSION['tmp-kernel-file'] && $_SESSION['dl-kernel-file']) {
                if ($_REQUEST['msg'] == 'dl') {
                    $fp = fopen($_SESSION['tmp-kernel-file'],'wb');
                    if (!$fp) throw new Exception(_('Error: Failed to open temp file'));
                    self::$FOGURLRequests->process(mb_convert_encoding($_SESSION['dl-kernel-file'],'UTF-8'),'GET',false,false,false,false,$fp);
                    if (!file_exists($_SESSION['tmp-kernel-file'])) throw new Exception(_('Error: Failed to download kernel'));
                    if (!filesize($_SESSION['tmp-kernel-file']) >  1048576) throw new Exception(sprintf('%s: %s: %s - %s',_('Error'),_('Download Failed'),_('Failed'),_('filesize'),filesize($_SESSION['tmp-kernel-file'])));
                    $SendME = '##OK##';
                } else if ($_REQUEST['msg'] == 'tftp') {
                    $destfile = $_SESSION['dest-kernel-file'];
                    $tmpfile = $_SESSION['tmp-kernel-file'];
                    unset($_SESSION['dest-kernel-file'],$_SESSION['tmp-kernel-file'],$_SESSION['dl-kernel-file']);
                    self::$FOGFTP->set('host',$this->getSetting('FOG_TFTP_HOST'))
                        ->set('username',trim($this->getSetting('FOG_TFTP_FTP_USERNAME')))
                        ->set('password',$this->getSetting('FOG_TFTP_FTP_PASSWORD'));
                    if (!self::$FOGFTP->connect()) throw new Exception(_('Error: Unable to connect to tftp server'));
                    $orig = sprintf('/%s/%s',trim($this->getSetting('FOG_TFTP_PXE_KERNEL_DIR'),'/'),$destfile);
                    $backuppath = sprintf('/%s/backup/',dirname($orig));
                    $backupfile = sprintf('%s%s_%s',$backuppath,$destfile,$this->formatTime('','Ymd_His'));
                    if (self::$FOGFTP->exists($backuppath)) self::$FOGFTP->mkdir($backuppath);
                    self::$FOGFTP->delete($orig);
                    if (!(self::$FOGFTP->rename($orig,$backupfile) || self::$FOGFTP->put($orig,$tmpfile))) throw new Exception(_('Error: Failed to install new kernel'));
                    self::$FOGFTP->close();
                    @unlink($tmpfile);
                    $SendME = '##OK##';
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        self::$FOGFTP->close();
        echo $SendME;
    }
    public function loginInfo() {
        $data = self::$FOGURLRequests->process(array('http://fogproject.org/globalusers','http://fogproject.org/version/version.php'),'GET');
        if (!$data[0]) $data['error-sites'] = _('Error contacting server');
        else $data['sites'] = $data[0];
        if (!$data[1]) $data['error-version'] = _('Error contacting server');
        else $data['version'] = $data[1];
        echo json_encode($data);
        exit;
    }
    public function getmacman() {
        try {
            if (!$_SESSION['AllowAJAXTasks']) throw new Exception(_('FOG Session Invalid'));
            if (!self::$FOGCore->getMACLookupCount()) throw new Exception(sprintf('<a href="?node=about&sub=mac-list">%s</a>',_('Load MAC Vendors')));
            $MAC = self::getClass('MACAddress',$_REQUEST['prefix']);
            $prefix = $MAC->getMACPrefix();
            if (!$MAC->isValid() || !$prefix) throw new Exception(_('Unknown'));
            $OUI = self::getClass('OUIManager')->find(array('prefix'=>$prefix));
            $OUI = @array_shift($OUI);
            if (!(($OUI instanceof OUI) && $OUI->isValid())) throw new Exception(_('Not found'));
            $Data = sprintf('<small>%s</small>',$OUI->get('name'));
        } catch (Exception $e) {
            $Data = sprintf('<small>%s</small>',$e->getMessage());
        }
        echo $Data;
        exit;
    }
    public function delete() {
        $this->title = sprintf('%s: %s',_('Remove'),$this->obj->get('name'));
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            sprintf('%s <b>%s</b>',_('Please confirm you want to delete'),$this->obj->get('name')) => '&nbsp;',
            ($this->obj instanceof Group ? _('Delete all hosts within group') : null) => ($this->obj instanceof Group ? '<input type="checkbox" name="massDelHosts" value="1" />' : null),
            ($this->obj instanceof Image || $this->obj instanceof Snapin ? _('Delete file data') : null) => ($this->obj instanceof Image || $this->obj instanceof Snapin ? '<input type="checkbox" name="andFile" id="andFile" value="1"/>' : null),
            '&nbsp;' => '<input type="submit" value="${label}"/>',
        );
        $fields = array_filter($fields);
        self::$HookManager->processEvent(sprintf('%s_DEL_FIELDS',strtoupper($this->node)),array($this->childClass=>&$this->obj));
        foreach($fields AS $field => &$input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
                'label' => $this->title,
            );
        }
        unset($input);
        self::$HookManager->processEvent(sprintf('%S_DEL',strtoupper($this->childClass)),array($this->childClass=>&$this->obj));
        printf('<form method="post" action="%s" class="c">',$this->formAction);
        $this->render();
        printf('</form>');
    }
    public function configure() {
        $randTime = mt_rand(0,90);
        $setTime = (int) $this->getSetting('FOG_SERVICE_CHECKIN_TIME');
        $randTime += $setTime;
        unset($setTime);
        echo "#!ok\n#sleep=$randTime\n#force={$this->getSetting(FOG_TASK_FORCE_REBOOT)}\n#maxsize={$this->getSetting(FOG_CLIENT_MAXSIZE)}\n#promptTime={$this->getSetting(FOG_GRACE_TIMEOUT)}";
        unset($randTime);
        exit;
    }
    public function authorize() {
        try {
            $Host = $this->getHostItem(true);
            $key = bin2hex($this->certDecrypt($_REQUEST['sym_key']));
            $token = bin2hex($this->certDecrypt($_REQUEST['token']));
            if ($Host->get('sec_tok') && $token !== $Host->get('sec_tok')) {
                $Host->set('pub_key',null)->save();
                throw new Exception('#!ist');
            }
            if ($Host->get('sec_tok') && !$key) throw new Exception('#!ihc');
            $Host
                ->set('sec_time',$this->nice_date('+30 minutes')->format('Y-m-d H:i:s'))
                ->set('pub_key',$key)
                ->set('sec_tok',$this->createSecToken())
                ->save();
            printf('#!en=%s',$this->certEncrypt("#!ok\n#token={$Host->get(sec_tok)}",$Host));
        }
        catch (Exception $e) {
            echo  $e->getMessage();
        }
        exit;
    }
    public function requestClientInfo() {
        if (!isset($_REQUEST['newService'])) {
            print_r(array_keys($this->getGlobalModuleStatus()));
            exit;
        }
        $globalModules = array_diff(array_keys(array_filter($this->getGlobalModuleStatus())),array('dircleanup','usercleanup','clientupdater','hostregister'));
        $Host = $this->getHostItem(true,false,true,false,isset($_REQUEST['newService']));
        $hostModules = $this->getSubObjectIDs('Module',array('id'=>$Host->get('modules')),'shortName');
        $hostModules = array_values(array_intersect($globalModules,(array)$hostModules));
        $array = array();
        foreach ($hostModules AS $i => &$key) {
            $hostNotNeeded = false;
            switch ($key) {
            case 'usertracker':
            case 'snapinclient':
                continue 2;
            case 'greenfog':
                $class='GF';
                break;
            case 'printermanager':
                $class='PrinterClient';
                break;
            case 'taskreboot':
                $class='Jobs';
                break;
            case 'usertracker':
                $class='UserTrack';
                break;
            default:
                $class=$key;
                break;
            }
            $array[$key] = self::getClass($class,true,false,false,false,isset($_REQUEST['newService']))->send();
            unset($key);
        }
        echo json_encode($array);
        exit;
    }
    public function clearAES() {
        if (isset($_REQUEST['groupid'])) self::getClass('HostManager')->update(array('id'=>self::getClass('Group',$_REQUEST['groupid'])->get('hosts')),'',array('pub_key'=>'','sec_tok'=>'','sec_time'=>'0000-00-00 00:00:00'));
        else if (isset($_REQUEST['id'])) self::getClass('HostManager')->update(array('id'=>$_REQUEST['id']),'',array('pub_key'=>'','sec_tok'=>'','sec_time'=>'0000-00-00 00:00:00'));
    }
    public function delete_post() {
        self::$HookManager->processEvent(sprintf('%s_DEL_POST',strtoupper($this->node)), array($this->childClass=>&$this->obj));
        try {
            if ($this->obj->get('protected')) throw new Exception(sprintf('%s %s',$this->childClass,_('is protected, removal not allowed')));
            if ($this->obj instanceof Group) {
                if (isset($_REQUEST['delHostConfirm'])) self::getClass('HostManager')->destroy(array('id'=>$this->obj->get('hosts')));
                if (isset($_REQUEST['massDelHosts'])) $this->redirect("?node=group&sub=delete_hosts&id={$this->obj->get(id)}");
            }
            if (isset($_REQUEST['andFile'])) $this->obj->deleteFile();
            if (!$this->obj->destroy()) throw new Exception(_('Failed to destroy'));
            self::$HookManager->processEvent(sprintf('%s_DELETE_SUCCESS',strtoupper($this->childClass)), array($this->childClass=>&$this->obj));
            $this->setMessage(sprintf('%s %s: %s',$this->childClass,_('deleted'),$this->obj->get('name')));
            $this->resetRequest();
            $this->redirect(sprintf('?node=%s',$this->node));
        } catch (Exception $e) {
            self::$HookManager->processEvent(sprintf('%s_DELETE_FAIL',strtoupper($this->node)),array($this->childClass=>&$this->obj));
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }
    public function search() {
        if (in_array($this->node,array('task','tasks')) && (!$_REQUEST['sub'] || $_REQUEST['sub'] != 'search')) $this->redirect(sprintf('?node=%s&sub=active',$this->node));
        $eventClass = $this->childClass;
        if ($this->childClass == 'Task') $eventClass = 'host';
        $this->title = _('Search');
        if (in_array($this->node,self::$searchPages)) $this->searchFormURL = sprintf('?node=%s&sub=search',$this->node);
        self::$HookManager->processEvent(sprintf('%s_DATA',strtoupper($eventClass)),array('data'=>&$this->data,'templates'=>&$this->templates,'headerData'=>&$this->headerData,'attributes'=>&$this->attributes,'title'=>&$this->title,'searchFormURL'=>&$this->searchFormURL));
        self::$HookManager->processEvent(sprintf('%s_HEADER_DATA',strtoupper($this->childClass)),array('headerData'=>&$this->headerData));
        $this->render();
    }
    public function membership() {
        $objType = ($this->obj instanceof Host);
        $this->data = array();
        echo '<!-- Membership -->';
        printf('<div id="%s-membership">',$this->node);
        $this->headerData = array(
            sprintf('<input type="checkbox" name="toggle-checkbox%s1" class="toggle-checkbox1"',$this->node),
            sprintf('%s %s',($objType ? _('Group') : _('Host')),_('Name')),
        );
        $this->templates = array(
            '<input type="checkbox" name="host[]" value="${host_id}" class="toggle-'.($objType ? 'group' : 'host').'${check_num}" />',
            sprintf('<a href="?node=%s&sub=edit&id=${host_id}" title="Edit: ${host_name}">${host_name}</a>',($objType ? 'group' : 'host')),
        );
        $this->attributes = array(
            array('width'=>16,'class'=>'l filter-false'),
            array('width'=>150,'class'=>'l'),
        );
        $ClassCall = ($objType ? 'Group' : 'Host');
        foreach(self::getClass($ClassCall)->getManager()->find(array('id'=>$this->obj->get(sprintf('%ssnotinme',strtolower($ClassCall))))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $this->data[] = array(
                'host_id'=>$Host->get('id'),
                'host_name'=>$Host->get('name'),
                'check_num'=>1,
            );
            unset ($Host);
        }
        if (count($this->data) > 0) {
            self::$HookManager->processEvent(sprintf('OBJ_%s_NOT_IN_ME',strtoupper($ClassCall)),array('headerData' => &$this->headerData,'data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
            printf('<form method="post" action="%s"><label for="%sMeShow"><p class="c">%s %ss %s %s&nbsp;&nbsp;<input type="checkbox" name="%sMeShow" id="%sMeShow"/></p></label><div id="%sNotInMe"><h2>%s %s</h2>',
                $this->formAction,
                strtolower($ClassCall),
                _('Check here to see'),
                strtolower($ClassCall),
                _('not within this'),
                $this->node,
                strtolower($ClassCall),
                strtolower($ClassCall),
                strtolower($ClassCall),
                _('Modify Membership for'),$this->obj->get('name')
            );
            $this->render();
            printf('</div><br/><p class="c"><input type="submit" value="%s %s(s) to %s" name="addHosts"/></p><br/>',
                _('Add'),
                ($objType ? _('Group') : _('Host')),
                $this->node
            );
        }
        unset($this->data);
        $this->headerData = array(
            '<input type="checkbox" name="toggle-checkbox" class="toggle-checkboxAction"/>',
            sprintf('%s %s',_($ClassCall), _('Name')),
        );
        $this->templates = array(
            '<input type="checkbox" name="hostdel[]" value="${host_id}" class="toggle-action"/>',
            sprintf('<a href="?node=%s&sub=edit&id=${host_id}" title="Edit: ${host_name}">${host_name}</a>',strtolower($ClassCall)),
        );
        foreach(self::getClass($ClassCall)->getManager()->find(array('id'=>$this->obj->get(strtolower($ClassCall).'s'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $this->data[] = array(
                'host_id'=>$Host->get('id'),
                'host_name'=>$Host->get('name'),
            );
            unset($Host);
        }
        self::$HookManager->processEvent('OBJ_MEMBERSHIP',array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        printf('<form method="post" action="%s">',$this->formAction);
        $this->render();
        if (count($this->data)) printf('<p class="c"><input type="submit" value="%s %ss %s %s" name="remhosts"/></p>',_('Delete Selected'),$ClassCall,_('From'),$this->node);
    }
    public function membership_post() {
        if (self::$ajax) return;
        if (isset($_REQUEST['addHosts'])) $this->obj->addHost($_REQUEST['host']);
        if (isset($_REQUEST['remhosts'])) $this->obj->removeHost($_REQUEST['hostdel']);
        if ($this->obj->save(false)) {
            $this->setMessage(sprintf('%s %s',$this->obj->get('name'),_('saved successfully')));
            $this->redirect($this->formAction);
        }
    }
    public function wakeEmUp() {
        self::getClass('WakeOnLan',$_REQUEST['mac'])->send();
    }
    public function import() {
        $this->title = sprintf('Import %s List',$this->childClass);
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        echo _('This page allows you to upload a CSV file into FOG to ease migration. It will operate based on the fields that are normally required by each area.  For example, Hosts will have macs, name, description, etc....');
        printf('<form enctype="multipart/form-data" method="post" action="%s">',$this->formAction);
        $fields = array(
            _('CSV File') => '<input class="smaller" type="file" name="file" />',
            '&nbsp;' => sprintf('<input class="smaller" type="submit" value="%s"/>',_('Upload CSV')),
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        self::$HookManager->processEvent(sprintf('%s_IMPORT_OUT',strtoupper($this->childClass)),array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function export() {
        $this->title = sprintf('Export %s',$this->childClass);
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $fields = array(
            _(sprintf("Click the button to download the %s's table backup.",strtolower($this->childClass))) => sprintf('<input type="submit" value="%s"/>',_('Export')),
        );
        $report = self::getClass('ReportMaker');
        $this->array_remove('id',$this->databaseFields);
        foreach ((array)self::getClass($this->childClass)->getManager()->find() AS $i => &$Item) {
            if (!$Item->isValid()) continue;
            if ($this->childClass == 'Host') {
                if (!$Item->get('mac')->isValid()) continue;
                ob_start();
                echo $Item->get('mac')->__toString();
                foreach ((array)$Item->get('additionalMACs') AS $i => &$AddMAC) {
                    if (!$AddMAC->isValid()) continue;
                    printf('|%s',$AddMAC->__toString());
                    unset($AddMAC);
                }
                $macColumn = ob_get_clean();
                $report->addCSVCell($macColumn);
            }
            foreach (array_keys((array)$this->databaseFields) AS $i => &$field) {
                $report->addCSVCell($Item->get($field));
                unset($field);
            }
            self::$HookManager->processEvent(sprintf('%s_EXPORT_REPORT',strtoupper($this->childClass)),array('report'=>&$report,$this->childClass=>&$Item));
            $report->endCSVLine();
            unset($Item);
        }
        $_SESSION['foglastreport']=serialize($report);
        printf('<form method="post" action="export.php?type=%s">',strtolower($this->childClass));
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        self::$HookManager->processEvent(sprintf('%s_EXPORT',strtoupper($this->childClass)),array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</form>';
    }
    public function import_post() {
        try {
            if ($_FILES['file']['error'] > 0) throw new Exception(sprintf('Error: '.(is_array($_FILES['file']['error']) ? implode(', ',$_FILES['file']['error']) : $_FILES['file']['error'])));
            $file = sprintf('%s%s%s',dirname($_FILES['file']['tmp_name']),DIRECTORY_SEPARATOR,basename($_FILES['file']['tmp_name']));
            if (!file_exists($file)) throw new Exception(_('Could not find temp filename'));
            $numSuccess = $numFailed = $numAlreadExist = 0;
            $handle = fopen($file,'rb');
            $this->array_remove('id',$this->databaseFields);
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $totalRows++;
                try {
                    $Item = self::getClass($this->childClass);
                    if ($Item instanceof Host) {
                        $ModuleIDs = $this->getSubObjectIDs('Module','','id');
                        $MACs = $this->parseMacList($data[0]);
                        $Host = self::getClass('HostManager')->getHostByMacAddresses($MACs);
                        if ($Host && $Host->isValid()) throw new Exception(_('Host already exists with at least one of the listed MACs'));
                        $PriMAC = array_shift($MACs);
                        $iterator = 1;
                    } else $iterator = 0;
                    if ($Item->getManager()->exists($data[$iterator])) throw new Exception(sprintf('%s %s: %s',$this->childClass,_('already exists with this name'),$data[$iterator]));
                    foreach (array_keys((array)$this->databaseFields) AS $i => $field) {
                        if ($Item instanceof Host) $i++;
                        if (isset($field) && $field === 'productKey') {
                            $test_encryption = $this->aesdecrypt($data[$i]);
                            if ($test_base64 = base64_decode($data[$i])) $data[$i] = $this->aesencrypt($test_base64);
                            else if (empty($test_encryption) || !mb_detect_encoding($test_encryption,'UTF-8',true)) $data[$i] = $this->aesencrypt($data[$i]);
                        }
                        $Item->set($field,$data[$i],($field == 'password'));
                    }
                    if ($Item instanceof Host) {
                        $Item->addModule($ModuleIDs)
                            ->addPriMAC($PriMAC)
                            ->addAddMAC($MACs);
                        unset($ModuleIDs,$MACs,$PriMAC);
                    }
                    if ($Item->save()) {
                        self::$HookManager->processEvent(strtoupper($this->childClass).'_IMPORT',array('data'=>&$data,$this->childClass=>&$Item));
                        $numSuccess++;
                    } else $numFailed++;
                } catch (Exception $e) {
                    $numFailed++;
                    $uploadErrors .= sprintf('%s #%s: %s<br/>',_('Row'),$totalRows,$e->getMessage());
                }
            }
            fclose($handle);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        $this->title = sprintf('%s %s %s',_('Import'),$this->childClass,_('Results'));
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        $fields = array(
            _('Total Rows') => $totalRows,
            sprintf('%s %ss',_('Successful'),$this->childClass) => $numSuccess,
            sprintf('%s %ss',_('Failed'),$this->childClass) => $numFailed,
            _('Errors') => $uploadErrors,
        );
        foreach ((array)$fields AS $field => &$input) {
            $this->data[] = array(
                'field'=>$field,
                'input'=>$input,
            );
        }
        unset($input);
        self::$HookManager->processEvent(sprintf('%s_IMPORT_FIELDS',strtoupper($this->childClass)),array('headerData'=>&$this->headerData,'data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
    }
}
