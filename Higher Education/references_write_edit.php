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

    if ($higherEducationReferenceComponentID == '' or $gibbonSchoolYearID == '') {
        $page->addError(__m('You have not specified a reference.'));
    } else {
        try {
            $result = $referenceGateway->selectReferencePersonByComponentID($higherEducationReferenceComponentID);
        } catch (PDOException $e) {
            $page->addError($e->getMessage());
        }

        if ($result->rowCount() != 1) {
            $page->addError(__m('The selected reference does not exist.'));
        } else {
            // Let's go!
            $values = $result->fetch();

            $form = Form::create('referencesEdit', $session->get('absoluteURL').'/modules/'.$session->get('module')."/references_write_editProcess.php?higherEducationReferenceComponentID=$higherEducationReferenceComponentID&gibbonSchoolYearID=$gibbonSchoolYearID");

            $form->addHiddenValue('address', $session->get('address'));

            $form->setTitle(__m('Reference Information'));

            $row = $form->addRow();
                $row->addLabel('name', __m('Student'));
                $row->addTextField('name')->isRequired()->readonly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Student', false, false));

            $row = $form->addRow();
                $row->addLabel('type', __m('Type'));
                $row->addTextField('type')->isRequired()->readonly()->setValue($values['refType']);

            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('notes', __m('Reference Notes'))->description(__m('Information about this reference shared by the student.'));
                $column->addTextArea('notes')->setRows(5)->setClass('w-full')->readOnly()->setValue($values['notes']);

            $form->addRow()->addHeading(__('Useful Information'));

            $markbookUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'Markbook']);
            $externamAssessmentUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'External Assessment']);
            $activitiesUrl = Url::fromModuleRoute('Students', 'student_view_details.php')->withQueryParams(['gibbonPersonID' => $values['gibbonPersonIDStudent'], 'subpage' => 'Activities']);

            $row = $form->addRow();
                $row->addLabel('academic', __m('Academic'));
                $row->addContent(Format::link($markbookUrl, __m('Markbook'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));
                $row->addContent(Format::link($externamAssessmentUrl, __m('External Assessment'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));

            $row = $form->addRow();
                $row->addLabel('coCurricular', __m('Co-curricular'));
                $row->addContent(Format::link($activitiesUrl, __m('Activities'), ['class' => 'w-full ml-2 underline', 'target' => '_blank']));

            $gibbonModuleID = checkModuleReady('/modules/IB Diploma/index.php', $connection2);

            if ($gibbonModuleID != false) {
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
                    $column->addTextArea('higherEducationNotes')->setRows(5)->setClass('w-full')->readOnly()->setValue($rowNotes['referenceNotes']);
            }

            $form->addRow()->addHeading(__('Your Contribution'));

            $row = $form->addRow();
                $row->addLabel('type', __m('Type'));
                $row->addTextField('type')->required()->readonly()->setValue($values['type']);

            if (!empty($values['title'])) {
                $row = $form->addRow();
                    $row->addLabel('title', __m('Title'));
                    $row->addTextField('title')->required()->readonly()->setValue($values['title']);
            }


            $row = $form->addRow();
                $column = $row->addColumn();
                $column->addLabel('body', __m('Reference'))->description(__m($values['refType'] == 'US Reference' ? 'Maximum limit of 10,000 characters.' : 'Maximum limit of 2,000 characters.'));
                $column->addTextArea('notes')->setRows(20)->setClass('w-full')->maxLength($values['refType'] == 'US Reference' ? 10000 : 2000)->setValue($values['body']);


           $row = $form->addRow();
                if ($values['status'] == 'Complete') {
                    $row->addLabel('status', __('Status'));
                    $row->addSelect('status')->isRequired()->fromArray(array('Pending' =>__('Pending'), 'In Progress' => __('In Progress'), 'Complete' => __('Complete'), 'Cancelled' => __('Cancelled')))->placeholder()->setValue($values['status']);

                } elseif ($values['status'] == 'In Progress') {
                    $row->addLabel('status', __('Status'));
                    $row->addSelect('status')->isRequired()->fromArray(['In Progress' => __('In Progress'), 'Complete' => __('Complete'), 'Cancelled' => __('Cancelled')])->placeholder()->setValue($values['status']);

                }
            
    

             





                

//////////////////////////-/--/-/-/-/-/-/-/-/-/--/-/-/-/-//--/-/-/-/-/-/-/-/-/-///////////---------------//////


    // <tr>
    //     <td colspan=2 style='padding-top: 15px;'>
    //         <b>Reference *</b><br/>
    //         <span style="font-size: 90%"><i>
    //         <?php
    //         if ($row['refType'] == 'US Reference') {
    //             echo 'Maximum limit of 10,000 characters.';
    //         } else {
    //             echo 'Maximum limit of 2,000 characters.'; } ?>

    <!-- //         </i></span><br/>
    //         <textarea name="body" id="body" rows=20 style="width:738px; margin: 5px 0px 0px 0px"><?php echo $row['body'] ?></textarea>

    //         <script type="text/javascript"> -->
    <!-- //             var body=new LiveValidation('body');
    //             body.add(Validate.Presence);
    //             <?php
    //             if ($row['refType'] == 'US Reference') {
    //                 echo 'body.add( Validate.Length, { maximum: 10000 } );';
    //             } else {
    //                 echo 'body.add( Validate.Length, { maximum: 2000 } );';
    //             }
    //             ?>
    //             </script> -->
    //     </td>
    // </tr>

    <!-- // <tr>
    //     <td>
    //         <b>Status *</b><br/>
    //     </td>
    <!-- //     <td class="right">
    //         <select name="status" id="status" style="width: 302px">
    //             <option <?php if ($row['status'] == 'In Progress') { echo 'selected'; } ?> value='In Progress'>In Progress</option> ;
    //             <option <?php if ($row['status'] == 'Complete') { echo 'selected'; } ?> value='Complete'>Complete</option> ;
    //         </select>
    //     </td> -->
    // </tr>
    
    // <tr>
    //     <td colspan=2>
    //         <?php -->
    //         //$referenceGateway = $container->get(ReferenceGateway::class);

    //         // QUERY
    //         $criteria = $referenceGateway->newQueryCriteria(true)
    //             ->sortBy(['title'])
    //             ->pageSize(50)
    //             ->fromPOST();

    //         $references = $referenceGateway->queryReferenceComponentsByReference($criteria, $row['higherEducationReferenceID'], $higherEducationReferenceComponentID);

    //         $table = DataTable::createPaginated('contributions', $criteria);
    //             $table->setTitle(__m('Other Contributions'));
    //             $table->addExpandableColumn('body');
    //             $table->addColumn('name', __('Name'))->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', true, true]))->notSortable();
    //             $table->addColumn('status', __('Status'))->format(function($valuesContributions) use ($guid, $session) {
    //                 if ($valuesContributions['status'] == 'Complete') {
    //                     return "<img style='margin-right: 3px; float: left' title='Complete' src='./themes/".$session->get('gibbonThemeName')."/img/iconTick.png'/> <b>".$valuesContributions['status']."</b>";
    //                 } else {
    //                     return "<img style='margin-right: 3px; float: left' title='In Progress' src='./themes/".$session->get('gibbonThemeName')."/img/iconTick_light.png'/> <b> ".$valuesContributions['status']."</b>";
    //                 }
    //             });
    //             $table->addColumn('type', __('Type'));
    //             $table->addColumn('title', __('Title'));

    //         echo $table->render($references);
    //         ?>
    <!-- </td> -->
    <!-- </tr> -->

    <!-- <tr>
        <td>
            <span style="font-size: 90%"><i>* denotes a required field</i></span>
        </td> -->
        <!-- <td class="right">
            <input type="hidden" name="address" value="<?php print $_SESSION[$guid]["address"] ?>">
            <input type="submit" value="Submit">
        </td>
    </tr> -->
             


<!-- ///-/-/-/-/-/-/-/-/-/-/-/-/-/-/-/-/////////////--------------------------------------------/////-/--/-/-/-//-/-/-/-/--/-/-/ -->

             echo $form->getOutput();

        }
    }
}
?>
