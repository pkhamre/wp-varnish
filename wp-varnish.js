function addRow(tableID) {
	var tbody = document.getElementById(tableID).getElementsByTagName ('tbody')[0];

	var row = document.createElement ('tr');
	var td1 = document.createElement ('td');
	var td2 = document.createElement ('td');
	var wpv_addr = document.createElement ('input');
	var wpv_port = document.createElement ('input');

	wpv_addr.class = "regular-text";
	wpv_addr.type = "text";
	wpv_addr.id = "wpvarnish_addr[]";
	wpv_addr.name = "wpvarnish_addr[]";
	wpv_addr.value = "";

	wpv_port.class = "regular-text";
	wpv_port.type = "text";
	wpv_port.id = "wpvarnish_port[]";
	wpv_port.name = "wpvarnish_port[]";
	wpv_port.value = "";

	td1.appendChild (wpv_addr);
	td2.appendChild (wpv_port);
	row.appendChild (td1);
	row.appendChild (td2);
	tbody.appendChild (row);
}

function deleteRow(tableID, rowID) {
	try {
		var tbody = document.getElementById(tableID).getElementsByTagName ('tbody')[0];
		tbody.getElement
		var rowCount = tbody.rows.length;

		for (var i = 0; i < rowCount; i++) {
			var row = tbody.rows[i];
			var chkbox = row.cells[0].childNodes[0];
			if(null != chkbox && true == chkbox.checked) {
				tbody.deleteRow(i);
				rowCount--;
				i--;
			}

		}
	}catch(e) {
		alert(e);
	}
}
