<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$pageTitle = 'Управление титанами';
require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-dragon"></i> Стихии титанов</h4>
        </div>
        <div class="card-body">
            <!-- Форма добавления -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-lg-5">
                    <input type="text" id="newElementName" class="form-control" placeholder="Название стихии">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="input-group">
                        <input type="color" id="newElementColor" class="form-control form-control-color" value="#808080">
                        <span class="input-group-text">Цвет</span>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <button class="btn btn-success w-100" onclick="addElement()">
                        <i class="fas fa-plus"></i> Добавить
                    </button>
                </div>
            </div>
            
            <!-- Список стихий -->
            <div id="elementsList" class="row g-3">
                <div class="col-12 text-center text-muted">Загрузка...</div>
            </div>
        </div>
    </div>
</div>

<script>
function loadElements() {
    $.ajax({
        url: 'ajax/TitanHandler.php?action=get_elements',
        type: 'GET',
        dataType: 'json',
        success: function(elements) {
            if (elements.length === 0) {
                $('#elementsList').html('<div class="col-12"><div class="alert alert-info">Нет стихий. Добавьте первую!</div></div>');
                return;
            }
            
            let html = '';
            elements.forEach(function(el) {
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 40px; height: 40px; background-color: ${el.color}; border-radius: 50%; border: 1px solid #ddd;"></div>
                                    <span class="fw-bold">${escapeHtml(el.name)}</span>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="deleteElement(${el.id})" disabled>
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            $('#elementsList').html(html);
        },
        error: function() {
            $('#elementsList').html('<div class="col-12"><div class="alert alert-danger">Ошибка загрузки</div></div>');
        }
    });
}

function addElement() {
    const name = $('#newElementName').val().trim();
    const color = $('#newElementColor').val();
    
    if (!name) {
        alert('Введите название стихии');
        return;
    }
    
    $.ajax({
        url: 'ajax/TitanHandler.php',
        type: 'POST',
        data: { 
            action: 'add_element',
            name: name, 
            color: color 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#newElementName').val('');
                $('#newElementColor').val('#808080');
                loadElements();
            } else {
                alert('Ошибка: ' + response.error);
            }
        }
    });
}

function deleteElement(id) {
    
	
	if (confirm('Удалить стихию? Она будет удалена из истории боев.')) {
        $.ajax({
            url: 'ajax/TitanHandler.php',
            type: 'POST',
            data: { 
                action: 'delete_element',
                id: id 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadElements();
                } else {
                    alert('Ошибка: ' + response.error);
                }
            }
        });
    }
}

$(document).ready(function() {
    loadElements();
});
</script>

<?php require_once 'includes/footer.php'; ?>