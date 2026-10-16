
/* ÆTHERYX CLEAN NAVIGATION / EMPTY-STATE HELPERS */
(function(){
  'use strict';
  const isUsers = /\/frontend\/users\//i.test(location.pathname) || /\\frontend\\users\\/i.test(location.pathname);
  if(!isUsers) return;

  const path = location.pathname.split(/[\\/]/).pop();
  const userLinks = [
    ['▣','Dashboard','../hero/hero-dashboard.html','hero-dashboard.html'],
    ['◈','Marketplace','marketplace.html','marketplace.html'],
    ['▤','Order History','orders.html','orders.html'],
    ['▥','Inventory','inventory.html','inventory.html'],
    ['◫','Resources','resources.html','resources.html'],
    ['⌁','Compatibility','compatibility.html','compatibility.html'],
    ['◌','Notifications','notifications.html','notifications.html'],
    ['○','Profile','profile.html','profile.html']
  ];

  function createSidebar(){
    if(document.querySelector('.sidebar')) return;
    const aside=document.createElement('aside');
    aside.className='sidebar ax-generated-sidebar';
    aside.innerHTML=`
      <div class="brand">
        <div class="brand-symbol">Æ</div>
        <div><div class="brand-name">ÆTHERYX</div><div class="brand-sub">RESOURCE MANAGEMENT</div></div>
      </div>
      <nav aria-label="Primary navigation"></nav>
      <div class="sidebar-bottom">
        <div class="user-mini">
          <div class="avatar">A</div>
          <div class="user-name"><strong>Authenticated</strong><span>System user</span></div>
        </div>
      </div>`;
    const nav=aside.querySelector('nav');
    userLinks.forEach(([icon,label,href,file])=>{
      const a=document.createElement('a');
      a.href=href;
      a.className=file===path?'active':'';
      a.innerHTML=`<span class="nav-icon">${icon}</span><span class="nav-text">${label}</span>`;
      nav.appendChild(a);
    });
    document.body.prepend(aside);
  }

  function mobileToggle(){
    let btn=document.querySelector('.mobile-toggle');
    if(!btn){
      btn=document.createElement('button');
      btn.className='mobile-toggle ax-mobile-toggle';
      btn.type='button';
      btn.setAttribute('aria-label','Open navigation');
      btn.innerHTML='☰';
      document.body.appendChild(btn);
    }
    const side=document.querySelector('.sidebar');
    if(!side) return;
    btn.addEventListener('click',()=>side.classList.toggle('open'));
    document.addEventListener('click',e=>{
      if(!side.contains(e.target) && e.target!==btn) side.classList.remove('open');
    });
  }

  function inventoryEmpty(){
    if(path!=='inventory.html') return;
    const empty=document.getElementById('empty');
    if(empty && !empty.querySelector('.ax-shop-now')){
      empty.innerHTML=`<strong class="empty-title">No equipment in your inventory</strong><p class="empty-text">You don't have any active equipment assignments yet. Browse the marketplace and request equipment to get started.</p><a class="ax-shop-now btn btn-primary" href="marketplace.html">Shop Now</a>`;
    }
  }

  document.addEventListener('DOMContentLoaded',()=>{
    createSidebar();
    mobileToggle();
    inventoryEmpty();
  });
})();
