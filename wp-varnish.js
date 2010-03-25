// our id for our rows, we can have rows added and removed
// so we need a base
var rowCount = 0;

function createRow(tableID, id, addr, port) {
	var row = document.createElement ('tr');
	var td1 = document.createElement ('td');
	var td2 = document.createElement ('td');
	var td3 = document.createElement ('td');
	var wpv_addr = document.createElement ('input');
	var wpv_port = document.createElement ('input');
	var dRow = document.createElement ('input');

	wpv_addr.className = "regular-text";
	wpv_addr.type = "text";
	wpv_addr.id = id;
	wpv_addr.name = "wpvarnish_addr[]";
	wpv_addr.value = addr || "";

	wpv_port.className = "small-text";
	wpv_port.type = "text";
	wpv_port.id = id;
	wpv_port.name = "wpvarnish_port[]";
	wpv_port.value = port || "";

	dRow.className = "";
	dRow.type = "button";
	dRow.name = "deleteRow";
	dRow.value = "-";
	dRow.id = id;
	dRow.onclick = function () { deleteRow(tableID, id); }

	td1.appendChild (wpv_addr);
	td2.appendChild (wpv_port);
	td3.appendChild (dRow);
	row.appendChild (td1);
	row.appendChild (td2);
	row.appendChild (td3);

	return row;
}

function addRow(tableID, id, addr, port) {
	var tbody = document.getElementById(tableID).getElementsByTagName ('tbody')[0];

	rowCount++;
	var row = createRow(tableID, id, addr, port);

	tbody.appendChild (row);
}

function deleteRow(tableID, rowID) {
	try {
		var tbody = document.getElementById(tableID).getElementsByTagName ('tbody')[0];
		var trs = tbody.getElementsByTagName ('tr');

		// the id = 0 we don't want to remove, as it is the header
		for (var i = 1; i < trs.length; i++) {
			// we use our own id, let's not mix up with table ids
			var id = (trs[i].getElementsByTagName ('input')[0]).id;
			if (id == rowID) {
				tbody.deleteRow (i);
				return;
			}
		}
	} catch(e) {
		alert(e);
	}
}
