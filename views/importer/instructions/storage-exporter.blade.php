<?php $description = isset($description) ? $description : false; ?>
<p>This tool will migrate any media and documents you are currently hosting on your cloud storage and add it to your media library.</p>
@if (!$description)
@if ($background)
<p>Depending on the number of items you have, this could take anywhere from a minute to several hours.  This process runs in the background until it's finished.  Once you've started the process, please check this page for progress.</p>
@else
<p>Depending on the number of items you have, this could take anywhere from a minute to several hours.</p>
<p><strong>IMPORTANT:</strong> You are running the import process in the web browser.  <strong>Do not navigate away from this page or the import may not finish.</strong></p>
@endif
<p><strong>Note:</strong></p>
<ol>
	<li><strong>Always backup your database before performing any batch migration.</strong></li>
	<li>This process will try it's best to prevent duplicates, but a few might slip through.  Always double check the results.</li>
	<li>Did you backup your database?</li>
</ol>
@endif