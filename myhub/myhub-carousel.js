document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.bh-myhub-family-carousel').forEach(function(carousel){var track=carousel.querySelector('.bh-myhub-family-grid');var prev=carousel.querySelector('.bh-myhub-carousel-prev');var next=carousel.querySelector('.bh-myhub-carousel-next');if(!track)return;function update(){var max=track.scrollWidth-track.clientWidth-2;if(prev)prev.disabled=track.scrollLeft<=2;if(next)next.disabled=track.scrollLeft>=max;}function move(dir){var card=track.querySelector('.bh-myhub-child-card,.bh-myhub-empty-family');var amount=card?card.getBoundingClientRect().width+18:track.clientWidth*.8;track.scrollBy({left:dir*amount,behavior:'smooth'});}if(prev)prev.addEventListener('click',function(){move(-1);});if(next)next.addEventListener('click',function(){move(1);});track.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update);update();});});
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('.bh-myhub-custom-calendars-carousel').forEach(function(carousel){
  var track=carousel.querySelector('[data-custom-calendars-track]');
  var prev=carousel.querySelector('.bh-myhub-custom-calendar-prev');
  var next=carousel.querySelector('.bh-myhub-custom-calendar-next');
  if(!track)return;
  function update(){
   var max=track.scrollWidth-track.clientWidth-2;
   if(prev)prev.disabled=track.scrollLeft<=2;
   if(next)next.disabled=track.scrollLeft>=max;
  }
  function move(dir){
   var card=track.querySelector('.bh-myhub-custom-calendar-card');
   var amount=card?card.getBoundingClientRect().width+12:track.clientWidth*.8;
   track.scrollBy({left:dir*amount,behavior:'smooth'});
  }
  if(prev)prev.addEventListener('click',function(){move(-1);});
  if(next)next.addEventListener('click',function(){move(1);});
  track.addEventListener('scroll',update,{passive:true});
  window.addEventListener('resize',update);
  update();
 });
});
