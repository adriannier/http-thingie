var iOS = !!navigator.platform && /iPhone|iPod/.test(navigator.platform)
var iPad = !!navigator.platform && /iPad/.test(navigator.platform)
var isTouch = isTouchDevice()

var videoFirstTimeUpdate = true
var videoPlayedByUser = false
var videoAutomatedEvent = false

window.addEventListener('resize', windowResized)
window.addEventListener('load', documentLoaded)

document.onkeydown = function(evt) {
    
    evt = evt || window.event
    
    var action = ''
            
    if ("key" in evt) {
        
        if (evt.key === "Escape" || evt.key === "Esc") {
            action = 'left'
        } else if (evt.key === "ArrowLeft") {
            action = 'left'
        } else if (evt.key === "ArrowUp") {
            action = 'up'
        } else if (evt.key === "ArrowRight") {
            action = 'open'
        } else if (evt.key === "Enter") {
            action = 'open'
        } else if (evt.key === "Return") {
            action = 'open'
        } else if (evt.key === "ArrowDown") {
            action = 'down'
        }

    } else {
        
        if (evt.keyCode === 27) { // Escape
            action = 'left'
        } else if (evt.keyCode === 37) { // Left
            action = 'left'
        } else if (evt.keyCode === 38) { // Up
            action = 'up'
        } else if (evt.keyCode === 39) { // Right
            action = 'open'
        } else if (evt.keyCode === 10) { // Return
            action = 'open'
        } else if (evt.keyCode === 13) { // Enter
            action = 'open'
        } else if (evt.keyCode === 40) { // Down
            action = 'down'
        }
                        
    }
    
    
    if (action != '') {

        evt.stopPropagation()
        evt.preventDefault()
        
        // console.log('Keyboard action: ' + action)
        
        if (action == 'left') {

            backlinks = document.getElementsByClassName('back-link')
            
            if (backlinks.length > 0) {
                window.location.href = backlinks[0].href
            }
            
        } else if (action == 'down') {
    
            var items = document.getElementsByTagName('li')
            var currentItem = currentListItem()
            
            if (typeof currentItem === 'undefined') {
    
                selectListItem(items[0], true)
                                                        
            } else {
                
                index = parseInt(currentItem.id)
                
                if ((index + 1) < items.length ) {
                    selectListItem(items[index + 1], true)
                }

                                
            } 
        
        } else if (action == 'up') {
    
            var items = document.getElementsByTagName('li')
            var currentItem = currentListItem()
            
            if (typeof currentItem === 'undefined') {
                
                selectListItem(items[items.length - 1])
                scrollToItem(items[items.length - 1], true)
                          
            } else {
                
                index = parseInt(currentItem.id)
                
                if ((index - 1) >= 0 ) {
                    selectListItem(items[index - 1]) 
                    scrollToItem(items[index - 1], true)
                }
                                
            }   
            
        } else if (action == 'open') {
            
            var currentItem = currentListItem()
            
            if (typeof currentItem !== 'undefined') {
                
                performItemAction(currentItem)
                
            }
                
        }
 
    }
    
}

function loadViewerForItem(item) {

    var itemURL = item.querySelector('div.title a').href
    var video = document.getElementById('video')
    video.style.display = 'block'
    video.src = itemURL
    video.play()
    
}

function performItemAction(item) {
    
    console.log(item.classList)
    
    if (iOS || item.classList.contains('directory') || item.classList.contains('back-button')) {
        
        window.location.href = item.querySelector('div.title a').href
        
    } else {
        
        loadViewerForItem(item)
        
    }
    
}

function performItemActionAfterTogglingItem(item) {
    
    item.classList.add('selected')
    redraw(item)

    var timeoutFunction = function() {
       
        item.classList.remove('selected')
        redraw(item)
        
        var timeoutFunction2 = function() {
                            
            performItemAction(item)
            
        }
        
        setTimeout(timeoutFunction2.bind(item), 50)
        
        
        
    }

    setTimeout(timeoutFunction.bind(item), 50)
    
}

function selectListItem(item, scroll = false, event = false) {
    
    if (event.target.tagName == 'A' && typeof event.target.href != 'undefined') {

            // Do nothing
            
    } else {
        
        event.stopPropagation()
        event.preventDefault()
        
        if (iOS && !scroll) {

            performItemActionAfterTogglingItem(item)
                    
        } else {
        
            if (isTouch && (item.classList.contains('directory') || item.classList.contains('back-button'))) {
                
                // Touch directory or back button
                performItemActionAfterTogglingItem(item)
                
            } else if (event !== false && event.detail === 2) {
                
                // Desktop double-click
                performItemAction(item)
                
                
            } else {
            
                var currentItem = currentListItem()
                
                if (typeof currentItem !== 'undefined' && currentItem !== item) {
                    
                    currentItem.classList.remove('selected')
                    redraw(currentItem)
            
                }
                
                var timeoutFunction = function() {
                    
                    if (scroll) {
                        
                        scrollToItem(item)        
                
                        var timeoutFunction2 = function() {
                            
                            item.classList.add('selected')    
                            redraw(item)
                            
                            if (isTouch) {
                                loadViewerForItem(item)
                            }
                        }
                        
                        setTimeout(timeoutFunction2.bind(item), 10)
                
                    } else {
                        
                        item.classList.add('selected')
                        redraw(item)
                        
                        if (isTouch) {
                            loadViewerForItem(item)
                        }
                        
                    }
                
                }
                
                setTimeout(timeoutFunction.bind(item, scroll), 10)
        
            }
            
        }
            
    }
    
}

function scrollToItem(item) {
    
    var sidebar = document.getElementById('sidebar')
    
    var index = parseInt(item.id)
    
    if (index === 0) {

        sidebar.scrollTop = 0
            
    } else {
        
        var items = sidebar.getElementsByTagName('li')
        
        if (index === (items.length - 1)) {

            sidebar.scrollTo(0,document.body.scrollHeight)
            
        }
        
    }
    
    var y = yPositionBelowViewport(item)
    
    if (y > 0) {
        
        sidebar.scrollBy(0, y)
        
    } else {
        
        y = yPositionAboveViewport(item)    
        
        if (y < 0) {
            sidebar.scrollBy(0, y)    
        }
        
    }
    
}

function currentListItem() {
    
    var result = document.querySelectorAll('li.selected')
    
    if (typeof result === 'undefined') {
        
        return undefined

    } else if (result.length === 0) {
        
        return undefined
                
    } else {
        
        return result[0]
                    
    }

}

function yPositionAboveViewport(e) {
    
    var rect = e.getBoundingClientRect()
    
    return rect.top
}

function yPositionBelowViewport(e) {
    
    var rect = e.getBoundingClientRect()
    var h = (window.innerHeight || document.documentElement.clientHeight)
    
    return rect.bottom - h
}

function redraw(element){

    var n = document.createTextNode(' ')
    
    element.appendChild(n)
    
    n.parentElement.removeChild(n)

}

function viewerVisible() {
 
    var viewer = document.getElementById('viewer')
    var viewerDisplay = window.getComputedStyle(viewer).getPropertyValue('display')

    if (viewerDisplay == 'none') {
        return false
    } else {
        return true
    }
    
}

function documentLoaded() {

    var video = document.getElementById('video')

/*
    video.addEventListener('abort', function(event) { console.log(event.type) })
    video.addEventListener('canplay', function(event) { console.log(event.type) })
    video.addEventListener('canplaythrough', function(event) { console.log(event.type) })
    video.addEventListener('durationchange', function(event) { console.log(event.type) })
    video.addEventListener('emptied', function(event) { console.log(event.type) })
    video.addEventListener('ended', function(event) { console.log(event.type) })
    video.addEventListener('error', function(event) { console.log(event.type) })
    video.addEventListener('loadeddata', function(event) { console.log(event.type) })
    video.addEventListener('loadedmetadata', function(event) { console.log(event.type) })
    video.addEventListener('loadstart', function(event) { console.log(event.type) })
*/
   
    video.addEventListener('pause', function(event) { 
        
        if (videoAutomatedEvent) {
            console.log('Paused by automation')
            videoAutomatedEvent = false
        } else {
            console.log('Paused by user')
            videoPlayedByUser = false
        }
        
    })
    
    video.addEventListener('play', function(event){
        
        if (videoAutomatedEvent) {
            console.log('Played by automation')
            videoAutomatedEvent = false
        } else {
            console.log('Played by user')
            videoPlayedByUser = true
        }
                
    })
/*
    video.addEventListener('playing', function(event) { console.log(event.type) })
    video.addEventListener('progress', function(event) { console.log(event.type) })
    video.addEventListener('ratechange', function(event) { console.log(event.type) })
    video.addEventListener('seeked', function(event) { console.log(event.type) })
    video.addEventListener('seeking', function(event) { console.log(event.type) })
    video.addEventListener('stalled', function(event) { console.log(event.type) })
    video.addEventListener('suspend', function(event) { console.log(event.type) })
*/
    video.addEventListener('timeupdate', function(event) { 
        
        if (videoFirstTimeUpdate) {
            console.log('Played by user')
            videoPlayedByUser = true
            videoFirstTimeUpdate = false
        }
        
    })
    
/*
    video.addEventListener('volumechange', function(event) { console.log(event.type) })
    video.addEventListener('waiting', function(event) { console.log(event.type) })
*/
        
}

function windowResized() {
 
    if (typeof video !== 'undefined' && videoPlayedByUser) {
        
        var viewer = document.getElementById('viewer')
        var viewerDisplay = window.getComputedStyle(viewer).getPropertyValue('display')

        if (viewerVisible()) {

            if (video.paused) {
                
                console.log('Playing video')    
                videoAutomatedEvent = true
                video.play()
                
            }

        } else {  
               
            if (!video.paused) {
                
                console.log('Pausing video')    
                videoAutomatedEvent = true
                video.pause()

            }         
          
        }
    }
    
}

function isTouchDevice() {
    
    var prefixes = ' -webkit- -moz- -o- -ms- '.split(' ');
    
    if (('ontouchstart' in window) || window.DocumentTouch && document instanceof DocumentTouch) {
        return true;
    }

    // include the 'heartz' as a way to have a non matching MQ to help terminate the join
    // https://git.io/vznFH
    var query = ['(', prefixes.join('touch-enabled),('), 'heartz', ')'].join('');
    return window.matchMedia(query).matches;
}
