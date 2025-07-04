# Backup course (Life cycle step)
Custom course backup step which allows overriding site default settings for backups.

Installation
============
This is an admin plugin and should go into ``admin/tool/lcbackupcoursestep``.

Dependencies
============
This plugin depends on the following plugins:
* Life cycle: https://moodle.org/plugins/view/tool_lifecycle.
* The following refactoring is accepted https://github.com/learnweb/moodle-tool_lifecycle/pull/189

List of backed up courses
============
You can find the list of backed up courses under Life cycle settings.

**List of backed up courses (tool_lcbackupcoursestep)**

URL: https://{your.moodle.site}/admin/category.php?category=lifecycle_category

List of pending adhoc tasks for backups
============
You can find the list of pending adhoc tasks for backups under Life cycle settings.
This page allows users to delete failed tasks.

**Adhoc tasks for course backup (tool_lcbackupcoursestep)**

URL: https://{your.moodle.site}/admin/category.php?category=lifecycle_category