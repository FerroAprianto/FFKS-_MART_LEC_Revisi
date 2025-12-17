function openEdit(id, produk, jumlah) {
    const popup = document.getElementById("popup-edit");

    popup.style.display = "flex";
    document.getElementById("edit-id").value = id;
    document.getElementById("edit-produk").value = produk;
    document.getElementById("edit-jumlah").value = jumlah;
}

function closeEdit() {
    document.getElementById("popup-edit").style.display = "none";
}
