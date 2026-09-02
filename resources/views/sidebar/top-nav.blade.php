    <flux:header container class="bg-zinc-50 border-b border-zinc-200">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:brand href="{{ ('/') }}" name="chatApp." class="max-lg:hidden" />
        <flux:brand href="{{ ('/') }}" name="chatApp" class="max-lg:hidden! hidden" />

        <flux:navbar class="-mb-px max-lg:hidden">
            <flux:navbar.item icon="home" href="{{ ('/') }}" current>Home</flux:navbar.item>
            
            <flux:separator vertical variant="subtle" class="my-2"/>

             
        </flux:navbar>

        <flux:spacer />
 
        
    </flux:header>

    <flux:sidebar sticky collapsible="mobile" class="lg:hidden bg-zinc-50 border-r border-zinc-200">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="{{ ('/') }}"
                name="chat App."
            />

            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ ('/') }}" current>Home</flux:sidebar.item>
            
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            
            <flux:sidebar.item icon="information-circle" href="#">Privacy</flux:sidebar.item>
            <flux:sidebar.item icon="information-circle" href="#">Terms</flux:sidebar.item>
        </flux:sidebar.nav>
    </flux:sidebar>