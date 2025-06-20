<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Http\Url;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\HigherEducation\Domain\ReferenceGateway;

// Module includes
include __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Higher Education/references_write_edit.php') == false) {
    // Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('Write References'), 'references_write.php', [
        'gibbonSchoolYearID' => $_GET['gibbonSchoolYearID'] ?? '',
    ]);
    $page->breadcrumbs->add(__m('Edit Reference'));

    // Check if school year specified
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? '';
    $higherEducationReferenceComponentID = $_GET['higherEducationReferenceComponentID'] ?? '';
    $referenceGateway = $container->get(ReferenceGateway::class);

    if (empty($higherEducationReferenceComponentID) OR empty($gibbonSchoolYearID)) {
        $page->addError(__m('You have not specified a reference.'));
    } else {

        $result = $referenceGateway->selectReferencePersonByComponentID($higherEducationReferenceComponentID);

        if ($result->rowCount() != 1) {
            $page->addError(__m('The selected reference does not exist.'));
        } else {
            // Let's go!
            $values = $result->fetch();
            
            $form = Form::create('referencesEdit', $session->get('absoluteURL').'/modules/'.$session->get('module')."/references_write_editProcess.php?higherEducationReferenceComponentID=$higherEducationReferenceComponentID&gibbonSchoolYearID=$gibbonSchoolYearID");

            $form->addHiddenValue('address', $session->get('address'));

            $form->setTitle(__m('Reference Information'));

            $row = $form->addRow();
                $row->addLabel('gibbonPersonIDStudent', __m('Student'));
                $row->addTextField('gibbonPersonIDStudent')->readonly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Student', false, false));

            $row = $form->addRow();
                $row->addLabel('type', __m('Type'));
                $row->addTextField('type')->readonly()->setValue($values['refType']);

            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('notes', __m('Reference Notes'))->description(__m('Information about this reference shared by the student.'));
                $column->addTextArea('notes')->setRows(4)->setClass('w-full')->readonly()->setValue($values['notes']);

            $form->addRow()->addHeading(__('Useful Information'));

            $markbookUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'Markbook']);
            $externamAssessmentUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'External Assessment']);
            
            $row = $form->addRow();
                $row->addLabel('academic', __m('Academic'));
                $row->addContent(Format::link($markbookUrl, __m('Markbook'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));
                $row->addContent(Format::link($externamAssessmentUrl, __m('External Assessment'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));

            $activitiesUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'Activities']);
            $row = $form->addRow();
                $row->addLabel('coCurricular', __m('Co-curricular'));
                $row->addContent(Format::link($activitiesUrl, __m('Activities'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));

            $gibbonModuleID = checkModuleReady('/modules/IB Diploma/index.php', $connection2);

            if (!empty($gibbonModuleID)) {
                try {
                    $dataAction = ['gibbonModuleID' => $gibbonModuleID, 'actionName' => 'View CAS in Student Profile', 'gibbonRoleID' => $session->get('gibbonRoleIDCurrent')];
                    $sqlAction = 'SELECT gibbonAction.name FROM gibbonAction JOIN gibbonPermission ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID) JOIN gibbonRole ON (gibbonPermission.gibbonRoleID=gibbonRole.gibbonRoleID) WHERE (gibbonAction.name=:actionName) AND (gibbonPermission.gibbonRoleID=:gibbonRoleID) AND gibbonAction.gibbonModuleID=:gibbonModuleID';
                    $resultAction = $connection2->prepare($sqlAction);
                    $resultAction->execute($dataAction);
                } catch (PDOException $e) {
                }

                if ($resultAction->rowCount() > 0) {
                    try {
                        $dataHooks = [];
                        $sqlHooks = "SELECT * FROM gibbonHook WHERE type='Student Profile' AND name='IB Diploma CAS'";
                        $resultHooks = $connection2->prepare($sqlHooks);
                        $resultHooks->execute($dataHooks);
                    } catch (PDOException $e) {
                    }

                    if ($resultHooks->rowCount() == 1) {
                        $rowHooks = $resultHooks->fetch();
                        $options = unserialize($rowHooks['options']);
                        // Check for permission to hook
                        try {
                            $dataHook = ['gibbonRoleIDCurrent' => $session->get('gibbonRoleIDCurrent'), 'sourceModuleName' => $options['sourceModuleName']];
                            $sqlHook = "SELECT gibbonHook.name, gibbonModule.name AS module, gibbonAction.name AS action FROM gibbonHook JOIN gibbonModule ON (gibbonModule.name='".$options['sourceModuleName']."') JOIN gibbonAction ON (gibbonAction.name='".$options['sourceModuleAction']."') JOIN gibbonPermission ON (gibbonPermission.gibbonActionID=gibbonAction.gibbonActionID) WHERE gibbonAction.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE gibbonPermission.gibbonRoleID=:gibbonRoleIDCurrent AND name=:sourceModuleName) AND gibbonHook.type='Student Profile' ORDER BY name";
                            $resultHook = $connection2->prepare($sqlHook);
                            $resultHook->execute($dataHook);
                        } catch (PDOException $e) {
                        }

                        if ($resultHook->rowCount() == 1) {
                            $hookUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'hook' => $rowHooks['name'], 'module' => $options['sourceModuleName'], 'action' => $options['sourceModuleAction'], 'gibbonHookID' => $rowHooks['gibbonHookID']]);
                            $row->addContent(Format::link($hookUrl, __m($rowHooks['name']), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));
                        }
                    }
                }
            }

            $behaviourUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'Behaviour']);
            $attendanceUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'School Attendance']);

            $row = $form->addRow();
                $row->addLabel('miscellaneous', __m('Miscellaneous'));
                $row->addContent(Format::link($behaviourUrl, __m('Behaviour'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));
                $row->addContent(Format::link($attendanceUrl, __m('Attendance'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));

            
            try {
                $dataNotes = ['gibbonPersonID' => $values['gibbonPersonIDStudent']];
                $sqlNotes = 'SELECT * FROM higherEducationStudent WHERE gibbonPersonID=:gibbonPersonID';
                $resultNotes = $connection2->prepare($sqlNotes);
                $resultNotes->execute($dataNotes);
            } catch (PDOException $e) {
                $page->addWarning($e->getMessage());
            }

            if ($resultNotes->rowCount() == 1) {
                $rowNotes = $resultNotes->fetch();

                $row = $form->addRow();
                    $column = $row->addColumn();
                    $column->addLabel('higherEducationNotes', __m('Higher Education Notes'))->description(__m('Information about higher education in general shared by the student.'));
                    $column->addTextArea('higherEducationNotes')->setRows(5)->setClass('w-full')->readonly()->setValue($rowNotes['referenceNotes']);
            }

            $form->addRow()->addHeading(__('Your Contribution'));

            $row = $form->addRow();
                $row->addLabel('type', __m('Type'));
                $row->addTextField('type')->readonly()->setValue($values['type']);

            if (!empty($values['title'])) {
                $row = $form->addRow();
                    $row->addLabel('title', __m('Title'));
                    $row->addTextField('title')->readonly()->setValue($values['title']);
            }

            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('body', __m('Reference'))->description(__m($values['refType'] == 'US Reference' ? 'Maximum limit of 10,000 characters.' : 'Maximum limit of 2,000 characters.'));
                $column->addTextArea('body')->setRows(20)->setClass('w-full')->maxLength($values['refType'] == 'US Reference' ? 10000 : 2000)->setValue($values['body']);

           $row = $form->addRow();
                    $row->addLabel('status', __('Status'));
                    $row->addSelect('status')->required()->fromArray(['In Progress' => __('In Progress'), 'Complete' => __('Complete')])->placeholder()->selected($values['status']);

            $form->addRow()->addHeading(__m('Other Contributions'));
            
            // QUERY for DATATABLE
            $criteria = $referenceGateway->newQueryCriteria(true)
                ->sortBy(['title'])
                ->pageSize(50)
                ->fromPOST();

            $references = $referenceGateway->queryReferenceComponentsByReference($criteria, $values['higherEducationReferenceID'], $higherEducationReferenceComponentID);

            $table = $form->addRow()->addDataTable('contributions', $criteria)->withData($references);
                $table->addExpandableColumn('body');
                $table->addColumn('name', __('Name'))
                    ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', true, true]))
                    ->notSortable();
                    
                $table->addColumn('status', __('Status'))
                    ->format(function($valuesContributions) use ($guid, $session) {
                        if ($valuesContributions['status'] == 'Complete') {
                            return "<img style='margin-right: 3px; float: left' title='Complete' src='./themes/".$session->get('gibbonThemeName')."/img/iconTick.png'/> <b>".$valuesContributions['status']."</b>";
                        } else {
                            return "<img style='margin-right: 3px; float: left' title='In Progress' src='./themes/".$session->get('gibbonThemeName')."/img/iconTick_light.png'/> <b> ".$valuesContributions['status']."</b>";
                        }
                    });
                
                $table->addColumn('type', __('Type'));
                $table->addColumn('title', __('Title'));

            $form->loadAllValuesFrom($values);

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
