<style>
.input {
	color:#000;
}
B {
	display:block;
	margin-top:8px;
}
</style>
<div class='confpanel'>
<!form layout>
<h2><!=L(layout.id?"Clone layout":"Add new layout")></h2>
<b><!=L("Name")></b><!field *text layout.id><br/>
<!field hidden pe.try1><img src='images/cms/accept.png' alt='OK' class='accept' onclick='return document.forms["layout"].submit();'>
</form>
</div>


