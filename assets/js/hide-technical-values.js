(function (window, document) {
  'use strict';

  var hiddenRaw = Array.isArray(window.myModuleHiddenValueIds) ? window.myModuleHiddenValueIds : [];
  var hiddenSet = new Set(hiddenRaw.map(function (value) { return String(value); }));

  function getValueId(node) {
    if (!node || !node.getAttribute) return '';
    return String(
      node.getAttribute('data-onevalue') ||
      node.getAttribute('data-value-id') ||
      node.getAttribute('data-treevalue') ||
      ''
    );
  }

  function hideNode(node) {
    var target = node.closest('.line-block__item') || node;
    target.classList.add('my-hidden-technical-value');
  }

  function resetNode(node) {
    var target = node.closest('.line-block__item') || node;
    target.classList.remove('my-hidden-technical-value');
  }

  function applyListing(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('[data-onevalue]').forEach(function (node) {
      var id = getValueId(node);
      if (hiddenSet.has(id)) hideNode(node); else resetNode(node);
    });
  }

  function applyDetail() {
    document.querySelectorAll('.sku-props').forEach(function (container) {
      container.querySelectorAll('.sku-props__value').forEach(function (valueNode) {
        var nested = valueNode.querySelector('[data-onevalue], [data-value-id], [data-treevalue]');
        var id = getValueId(valueNode) || getValueId(nested);
        if (hiddenSet.has(id)) hideNode(valueNode); else resetNode(valueNode);
      });

      container.querySelectorAll('.sku-props__item').forEach(function (itemNode) {
        var active = itemNode.querySelector('.sku-props__value.active, .sku-props__value--active, .active');
        if (!active) return;
        var activeHidden = active.classList.contains('my-hidden-technical-value') || active.style.display === 'none';
        if (!activeHidden) return;

        var fallback = itemNode.querySelector('.sku-props__value:not(.my-hidden-technical-value):not([style*="display: none"])');
        if (fallback && typeof fallback.click === 'function') fallback.click();
      });
    });
  }

  function applyAll(root) {
    if (!hiddenSet.size) return;
    applyListing(root);
    applyDetail();
  }

  document.addEventListener('DOMContentLoaded', function () { applyAll(document); });
  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (node.nodeType === 1) applyAll(node);
      });
    });
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  document.addEventListener('onFinalActionSKUInfo', function () { applyAll(document); });
  document.addEventListener('onSkuChange', function () { applyAll(document); });
})(window, document);
