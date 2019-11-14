define(['jquery',
], function ($) {
  'use strict';

  var Pluswerk = Pluswerk || {};
  Pluswerk.CacheQueue = Pluswerk.CacheQueue || {};
  Pluswerk.CacheQueue.ToolbarItem = Pluswerk.CacheQueue.ToolbarItem || {};
  Pluswerk.CacheQueue.ToolbarItem.updateCounter = function() {
    var request = new $.ajax({
      url: TYPO3.settings.ajaxUrls['WarmUpQueueToolbarItem::getStatus'],
      data: {},
      success: function(result) {
        document.getElementById("pluswerk-warm-up-queue-count").innerHTML = result.warmUpQueueCount;
        if (result.warmUpQueueCount === 0) {
          document.getElementById("pluswerk-warm-up-queue-icon-ok").style.display = "inline";
          document.getElementById("pluswerk-warm-up-queue-icon-running").style.display = "none";
        } else {
          document.getElementById("pluswerk-warm-up-queue-icon-ok").style.display = "none";
          document.getElementById("pluswerk-warm-up-queue-icon-running").style.display = "inline";
        }

        setTimeout(function(){
          Pluswerk.CacheQueue.ToolbarItem.updateCounter();
        }, 2000);
      },
      error: function() {
        setTimeout(function(){
          Pluswerk.CacheQueue.ToolbarItem.updateCounter();
        }, 10000);
      },
      dataType: 'json'
    });
  };

  Pluswerk.CacheQueue.ToolbarItem.updateCounter();
});

